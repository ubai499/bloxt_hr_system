<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplianceReview;
use App\Models\User;
use App\Services\ComplianceMonitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplianceController extends Controller
{
    public function __construct(private ComplianceMonitor $monitor) {}

    public function index(Request $request): View|JsonResponse
    {
        $payload = [
            'metrics' => $this->monitor->metrics(),
            'actions' => $this->monitor->actionItems()->all(),
            'events' => $this->monitor->calendarEvents()->all(),
            'reviews' => ComplianceReview::query()->latest('review_date')->orderByDesc('id')->get()->map(fn (ComplianceReview $review) => $this->reviewRow($review))->values(),
            'employees' => User::role('employee')->where('status', '!=', 'Left')->with([
                'latestRightToWorkCheck', 'documents', 'compensations', 'attendanceRecords', 'currentSponsorship',
            ])->orderBy('name')->get()->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'checklist' => $this->monitor->employeeChecklist($employee),
            ])->values(),
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.compliance.index', [
            'payload' => $payload,
            'results' => ComplianceReview::RESULTS,
            'activeTab' => $this->tab($request),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.compliance.index', [
            'tab' => 'reviews',
            'new' => 1,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        ComplianceReview::create($this->reviewData($request));

        return $this->success($request, 'The internal compliance review has been recorded.', 'Review saved');
    }

    public function show(ComplianceReview $review): JsonResponse
    {
        return response()->json($this->reviewDetail($review));
    }

    public function update(Request $request, ComplianceReview $review): JsonResponse|RedirectResponse
    {
        $review->update($this->reviewData($request, $review));

        return $this->success($request, 'The internal compliance review has been updated.', 'Review updated');
    }

    public function destroy(Request $request, ComplianceReview $review): JsonResponse|RedirectResponse
    {
        $review->delete();

        return $this->success($request, 'The internal compliance review has been removed.', 'Review removed');
    }

    private function reviewRow(ComplianceReview $review): array
    {
        return [
            'id' => $review->id,
            'review_number' => $review->review_number,
            'review_date' => $review->review_date?->toDateString(),
            'reviewer' => $review->reviewer,
            'area' => $review->area,
            'issues_found' => $review->issues_found,
            'result' => $review->result,
            'due_date' => $review->due_date?->toDateString(),
            'overdue' => $review->isOverdue(),
            'details_url' => route('admin.compliance.reviews.show', $review),
            'update_url' => route('admin.compliance.reviews.update', $review),
            'destroy_url' => route('admin.compliance.reviews.destroy', $review),
        ];
    }

    private function reviewDetail(ComplianceReview $review): array
    {
        return $this->reviewRow($review) + [
            'employees_sampled' => $review->employees_sampled,
            'records_reviewed' => $review->records_reviewed,
            'actions_required' => $review->actions_required,
            'responsible_person' => $review->responsible_person,
            'completion_date' => $review->completion_date?->toDateString(),
            'evidence_ref' => $review->evidence_ref,
            'notes' => $review->notes,
        ];
    }

    private function reviewData(Request $request, ?ComplianceReview $review = null): array
    {
        foreach (['due_date', 'employees_sampled'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => $field === 'employees_sampled' ? 0 : null]);
            }
        }
        $data = $request->validate([
            'review_number' => ['required', 'string', 'max:50', Rule::unique('compliance_reviews', 'review_number')->ignore($review)],
            'review_date' => ['required', 'date_format:Y-m-d'],
            'reviewer' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'employees_sampled' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'records_reviewed' => ['nullable', 'string', 'max:255'],
            'issues_found' => ['nullable', 'string', 'max:5000'],
            'actions_required' => ['nullable', 'string', 'max:5000'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(ComplianceReview::RESULTS)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['review_number'] = trim($data['review_number']);
        $data['reviewer'] = filled($data['reviewer'] ?? null) ? trim($data['reviewer']) : $request->user()->name;
        $data['employees_sampled'] = (int) ($data['employees_sampled'] ?? 0);
        foreach (['area', 'records_reviewed', 'issues_found', 'actions_required', 'responsible_person', 'notes'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        $data['completion_date'] = $data['result'] === 'Completed' ? ($review?->completion_date?->toDateString() ?: now()->toDateString()) : null;

        return $data;
    }

    private function tab(Request $request): string
    {
        $tab = $request->query('tab', 'dashboard');

        return in_array($tab, ['dashboard', 'calendar', 'checklist', 'reviews'], true) ? $tab : 'dashboard';
    }

    private function success(Request $request, string $message, string $title): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('admin.compliance.index', ['tab' => 'reviews'])->with('success', $message)->with('toast_title', $title);
    }
}
