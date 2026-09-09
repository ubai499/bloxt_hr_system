<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $vacancies = $this->filteredQuery($request)->with(['department', 'candidates'])->get();
        $candidates = $vacancies->pluck('candidates')->flatten();
        $payload = [
            'vacancies' => $vacancies->map(fn (Vacancy $vacancy) => $this->vacancyRow($vacancy))->values(),
            'stats' => [
                'open' => $vacancies->where('status', 'Open')->count(),
                'in_progress' => $candidates->where('outcome', 'In Progress')->count(),
                'offered' => $candidates->where('outcome', 'Offered')->count(),
                'hired' => $candidates->where('outcome', 'Hired')->count(),
            ],
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.recruitment.index', [
            'payload' => $payload,
            'departments' => Department::query()->orderBy('name')->get(),
            'activeDepartments' => Department::active()->orderBy('name')->get(),
            'hiringManagers' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->pluck('name'),
            'employmentTypes' => Vacancy::EMPLOYMENT_TYPES,
            'vacancyStatuses' => Vacancy::STATUSES,
            'outcomes' => Candidate::OUTCOMES,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.recruitment.index', ['new' => 1]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Vacancy::create($this->vacancyData($request) + ['status' => 'Open']);

        return $this->success($request, 'The new vacancy has been added.');
    }

    public function update(Request $request, Vacancy $vacancy): JsonResponse|RedirectResponse
    {
        $vacancy->update($this->vacancyData($request, $vacancy));

        return $this->success($request, 'The vacancy has been updated.');
    }

    public function updateStatus(Request $request, Vacancy $vacancy): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Vacancy::STATUSES)]]);
        $vacancy->update($data);

        return $this->success($request, 'The vacancy status has been updated.');
    }

    public function destroy(Request $request, Vacancy $vacancy): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($vacancy) {
            $record = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
            $record->candidates()->delete();
            $record->delete();
        });

        return $this->success($request, 'The vacancy has been removed.');
    }

    public function storeCandidate(Request $request, Vacancy $vacancy): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $vacancy) {
            $record = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
            $this->ensureAcceptsCandidates($record);
            $record->candidates()->create($this->candidateData($request));
        });

        return $this->success($request, 'The candidate has been added.', 'Candidate saved');
    }

    public function updateCandidate(Request $request, Vacancy $vacancy, Candidate $candidate): JsonResponse|RedirectResponse
    {
        $this->ensureCandidate($vacancy, $candidate);
        $candidate->update($this->candidateData($request));

        return $this->success($request, 'The candidate record has been updated.', 'Candidate updated');
    }

    public function destroyCandidate(Request $request, Vacancy $vacancy, Candidate $candidate): JsonResponse|RedirectResponse
    {
        $this->ensureCandidate($vacancy, $candidate);
        $candidate->delete();

        return $this->success($request, 'The candidate has been removed.', 'Candidate removed');
    }

    public function export(Request $request): StreamedResponse
    {
        $sorting = $request->validate([
            'sort' => ['nullable', Rule::in(['job_title', 'department', 'status', 'candidate', 'application_date', 'source', 'outcome'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $rows = $this->filteredQuery($request)->with(['department', 'candidates'])->get()
            ->flatMap(function (Vacancy $vacancy) {
                $base = [
                    'job_title' => $vacancy->job_title,
                    'department' => $vacancy->department?->name ?? '',
                    'status' => $vacancy->status,
                ];
                $candidates = $vacancy->candidates;
                if ($candidates->isEmpty()) {
                    return collect([$base + ['candidate' => '', 'application_date' => '', 'source' => '', 'outcome' => '']]);
                }

                return $candidates->map(fn (Candidate $candidate) => $base + [
                    'candidate' => $candidate->name,
                    'application_date' => $candidate->application_date?->toDateString() ?? '',
                    'source' => $candidate->source ?? '',
                    'outcome' => $candidate->outcome,
                ]);
            });
        $sort = $sorting['sort'] ?? 'job_title';
        $rows = $rows->sortBy(fn ($row) => mb_strtolower((string) ($row[$sort] ?? '')), SORT_NATURAL | SORT_FLAG_CASE, ($sorting['direction'] ?? 'asc') === 'desc');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Job Title', 'Department', 'Status', 'Candidate', 'Applied', 'Source', 'Outcome']);
            foreach ($rows as $row) {
                $cells = [$row['job_title'], $row['department'], $row['status'], $row['candidate'], $row['application_date'], $row['source'], $row['outcome']];
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', $cell) ? "'".$cell : $cell, $cells));
            }
            fclose($stream);
        }, 'recruitment.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Vacancy::STATUSES)],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        return Vacancy::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['department'] ?? null, fn ($query, $department) => $query->where('department_id', $department))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('job_title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhere('hiring_manager', 'like', '%'.$search.'%')
                        ->orWhere('recruitment_channel', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw('opening_date is null')
            ->latest('opening_date')
            ->orderBy('id');
    }

    private function vacancyRow(Vacancy $vacancy): array
    {
        return [
            'id' => $vacancy->id,
            'job_title' => $vacancy->job_title,
            'department_id' => $vacancy->department_id,
            'department' => $vacancy->department?->name,
            'hiring_manager' => $vacancy->hiring_manager,
            'employment_type' => $vacancy->employment_type,
            'opening_date' => $vacancy->opening_date?->toDateString(),
            'closing_date' => $vacancy->closing_date?->toDateString(),
            'salary_range_min' => $vacancy->salary_range_min !== null ? (float) $vacancy->salary_range_min : null,
            'salary_range_max' => $vacancy->salary_range_max !== null ? (float) $vacancy->salary_range_max : null,
            'location' => $vacancy->location,
            'recruitment_channel' => $vacancy->recruitment_channel,
            'reason_for_vacancy' => $vacancy->reason_for_vacancy,
            'status' => $vacancy->status,
            'accepts_candidates' => $vacancy->acceptsCandidates(),
            'update_url' => route('admin.recruitment.update', $vacancy),
            'status_url' => route('admin.recruitment.status.update', $vacancy),
            'destroy_url' => route('admin.recruitment.destroy', $vacancy),
            'candidates_url' => route('admin.recruitment.candidates.store', $vacancy),
            'candidates' => $vacancy->candidates->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'application_date' => $candidate->application_date?->toDateString(),
                'source' => $candidate->source,
                'interview_records' => $candidate->interview_records,
                'outcome' => $candidate->outcome,
                'update_url' => route('admin.recruitment.candidates.update', [$vacancy, $candidate]),
                'destroy_url' => route('admin.recruitment.candidates.destroy', [$vacancy, $candidate]),
            ])->values(),
        ];
    }

    private function vacancyData(Request $request, ?Vacancy $vacancy = null): array
    {
        foreach (['department_id', 'opening_date', 'closing_date', 'salary_range_min', 'salary_range_max'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'job_title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'hiring_manager' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', Rule::in(Vacancy::EMPLOYMENT_TYPES)],
            'opening_date' => ['nullable', 'date_format:Y-m-d'],
            'closing_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('opening_date') ? ['after_or_equal:opening_date'] : [])],
            'salary_range_min' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', ...($request->filled('salary_range_min') ? ['gte:salary_range_min'] : [])],
            'location' => ['nullable', 'string', 'max:255'],
            'recruitment_channel' => ['nullable', 'string', 'max:255'],
            'reason_for_vacancy' => ['nullable', 'string', 'max:255'],
            'status' => [$vacancy ? 'required' : 'nullable', Rule::in(Vacancy::STATUSES)],
        ], [
            'closing_date.after_or_equal' => 'Closing date must be on or after the opening date.',
            'salary_range_max.gte' => 'The maximum salary must be at least the minimum salary.',
        ]);
        $data['job_title'] = trim($data['job_title']);
        foreach (['hiring_manager', 'location', 'recruitment_channel', 'reason_for_vacancy'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        if (! $vacancy) {
            unset($data['status']);
        }

        return $data;
    }

    private function candidateData(Request $request): array
    {
        if ($request->input('application_date') === '') {
            $request->merge(['application_date' => null]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'application_date' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', 'string', 'max:255'],
            'interview_records' => ['nullable', 'string', 'max:5000'],
            'outcome' => ['required', Rule::in(Candidate::OUTCOMES)],
        ]);
        $data['name'] = trim($data['name']);
        $data['source'] = filled($data['source'] ?? null) ? trim($data['source']) : null;
        $data['interview_records'] = filled($data['interview_records'] ?? null) ? trim($data['interview_records']) : null;

        return $data;
    }

    private function ensureAcceptsCandidates(Vacancy $vacancy): void
    {
        if (! $vacancy->acceptsCandidates()) {
            throw ValidationException::withMessages(['vacancy_id' => 'Candidates can only be added to open or on-hold vacancies.']);
        }
    }

    private function ensureCandidate(Vacancy $vacancy, Candidate $candidate): void
    {
        abort_unless($candidate->vacancy_id === $vacancy->id, 404);
    }

    private function success(Request $request, string $message, ?string $title = null): JsonResponse|RedirectResponse
    {
        $title ??= match ($request->route()?->getActionMethod()) {
            'store' => 'Vacancy created',
            'update' => 'Vacancy updated',
            'updateStatus' => 'Vacancy updated',
            'destroy' => 'Vacancy deleted',
            default => 'Recruitment saved',
        };

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('admin.recruitment.index')->with('success', $message)->with('toast_title', $title);
    }
}
