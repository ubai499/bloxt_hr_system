<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RightToWorkController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $payload = [
            'rows' => $rows->values(),
            'stats' => [
                'total' => $rows->count(),
                'due' => $rows->whereIn('status', ['Review Due', 'Expiring Soon', 'Follow-up Required', 'Expired'])->count(),
                'expired' => $rows->where('status', 'Expired')->count(),
                'missing' => $rows->where('status', 'Evidence Missing')->count(),
            ],
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.right-to-work.index', [
            'payload' => $payload,
            'filters' => $filters,
            'employees' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(['id', 'name']),
            'methods' => RightToWorkCheck::METHODS,
            'statuses' => RightToWorkCheck::STATUSES,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.right-to-work.index', array_filter([
            'new' => 1,
            'employee' => $request->query('employee'),
        ]));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->checkData($request);
        DB::transaction(function () use ($data) {
            $this->lockEmployee($data['employee_id']);
            RightToWorkCheck::create($data + ['status' => $this->recordedStatus($data)]);
        });

        return $this->success($request, 'The new check has been added to the employee\'s record.');
    }

    public function show(RightToWorkCheck $check): JsonResponse
    {
        $check->load('employee');

        return response()->json($this->checkRow($check));
    }

    public function update(Request $request, RightToWorkCheck $check): JsonResponse|RedirectResponse
    {
        $data = $this->checkData($request, $check);
        DB::transaction(function () use ($data, $check) {
            $this->lockEmployee($check->employee_id, false);
            $record = RightToWorkCheck::lockForUpdate()->findOrFail($check->id);
            unset($data['employee_id']);
            $record->update($data + ['status' => $this->recordedStatus($data)]);
        });

        return $this->success($request, 'The right-to-work check has been updated.', 'Check updated');
    }

    public function destroy(Request $request, RightToWorkCheck $check): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($check) {
            User::whereKey($check->employee_id)->lockForUpdate()->firstOrFail();
            RightToWorkCheck::lockForUpdate()->findOrFail($check->id)->delete();
        });

        return $this->success($request, 'The right-to-work check has been removed.', 'Check removed');
    }

    public function export(Request $request): StreamedResponse
    {
        $sorting = $request->validate([
            'sort' => ['nullable', Rule::in(['employee', 'nationality', 'status', 'method', 'last_check', 'permission_expiry', 'next_follow_up', 'responsible'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $rows = $this->rows($this->filters($request));
        $sort = $sorting['sort'] ?? 'employee';
        $rows = $rows->sortBy(fn ($row) => mb_strtolower((string) ($row[$sort] ?? '')), SORT_NATURAL | SORT_FLAG_CASE, ($sorting['direction'] ?? 'asc') === 'desc');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Employee', 'Nationality', 'Status', 'Method', 'Last Check', 'Permission Expiry', 'Next Follow-up', 'Responsible']);
            foreach ($rows as $row) {
                $cells = [$row['employee'], $row['nationality'], $row['status'], $row['method'], $row['last_check'] ?? '', $row['permission_expiry'] ?? '', $row['next_follow_up'] ?? '', $row['responsible']];
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', (string) $cell) ? "'".$cell : $cell, $cells));
            }
            fclose($stream);
        }, 'right-to-work-status.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{status: string, search: string, employee: string}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', ...RightToWorkCheck::STATUSES])],
            'search' => ['nullable', 'string', 'max:255'],
            'employee' => ['nullable', 'regex:/^[1-9][0-9]*$/'],
        ]);

        return [
            'status' => $data['status'] ?? 'all',
            'search' => $data['search'] ?? '',
            'employee' => $data['employee'] ?? '',
        ];
    }

    private function rows(array $filters)
    {
        $search = trim($filters['search']);
        $rows = User::role('employee')->where('status', '!=', 'Left')
            ->with('latestRightToWorkCheck')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_number', 'like', '%'.$search.'%')
                        ->orWhere('nationality', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['employee'] !== '', fn (Builder $query) => $query->whereKey($filters['employee']))
            ->orderBy('name')
            ->get()
            ->map(fn (User $employee) => $this->employeeRow($employee));

        if ($filters['status'] !== 'all') {
            $rows = $rows->filter(fn ($row) => $row['status'] === $filters['status']);
        }

        return $rows;
    }

    private function employeeRow(User $employee): array
    {
        $check = $employee->latestRightToWorkCheck;

        return [
            'employee_id' => $employee->id,
            'employee' => $employee->name,
            'nationality' => $employee->nationality,
            'status' => $check?->displayStatus() ?? 'Evidence Missing',
            'method' => $check?->check_method,
            'last_check' => $check?->check_date?->toDateString(),
            'permission_expiry' => $check?->permission_expiry?->toDateString(),
            'next_follow_up' => $check?->next_check_date?->toDateString(),
            'evidence' => (bool) $check?->evidence_reference,
            'responsible' => $check?->performed_by,
            'check_id' => $check?->id,
            'profile_url' => route('admin.employees.show', ['employee' => $employee, 'tab' => 'rtw']),
            'details_url' => $check ? route('admin.right-to-work.show', $check) : null,
            'update_url' => $check ? route('admin.right-to-work.update', $check) : null,
            'destroy_url' => $check ? route('admin.right-to-work.destroy', $check) : null,
        ];
    }

    private function checkRow(RightToWorkCheck $check): array
    {
        return [
            'id' => $check->id,
            'employee_id' => $check->employee_id,
            'employee' => $check->employee->name,
            'check_date' => $check->check_date->toDateString(),
            'check_method' => $check->check_method,
            'performed_by' => $check->performed_by,
            'immigration_category' => $check->immigration_category,
            'restrictions' => $check->restrictions,
            'permission_start' => $check->permission_start?->toDateString(),
            'permission_expiry' => $check->permission_expiry?->toDateString(),
            'follow_up_required' => $check->follow_up_required,
            'next_check_date' => $check->next_check_date?->toDateString(),
            'evidence_reference' => $check->evidence_reference,
            'notes' => $check->notes,
            'status' => $check->displayStatus(),
            'update_url' => route('admin.right-to-work.update', $check),
            'destroy_url' => route('admin.right-to-work.destroy', $check),
        ];
    }

    private function checkData(Request $request, ?RightToWorkCheck $check = null): array
    {
        foreach (['permission_start', 'permission_expiry', 'next_check_date'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'employee_id' => [$check ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'check_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'check_method' => ['required', Rule::in(RightToWorkCheck::METHODS)],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'immigration_category' => ['nullable', 'string', 'max:255'],
            'restrictions' => ['nullable', 'string', 'max:255'],
            'permission_start' => ['nullable', 'date_format:Y-m-d'],
            'permission_expiry' => ['nullable', 'date_format:Y-m-d', ...($request->filled('permission_start') ? ['after_or_equal:permission_start'] : [])],
            'follow_up_required' => ['nullable', 'boolean'],
            'next_check_date' => ['nullable', 'required_if:follow_up_required,1,true,yes', 'date_format:Y-m-d'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'permission_expiry.after_or_equal' => 'Permission expiry must be on or after the permission start date.',
            'next_check_date.required_if' => 'Enter the next check date when a follow-up is required.',
        ]);
        $data['follow_up_required'] = $request->boolean('follow_up_required');
        $data['performed_by'] = filled($data['performed_by'] ?? null) ? trim($data['performed_by']) : $request->user()->name;
        foreach (['immigration_category', 'restrictions', 'evidence_reference', 'notes'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }

        return $data;
    }

    private function recordedStatus(array $data): string
    {
        if ($data['follow_up_required']) {
            return 'Follow-up Required';
        }

        return filled($data['permission_expiry'] ?? null) ? 'Review Due' : 'Valid';
    }

    private function lockEmployee(int $id, bool $currentOnly = true): User
    {
        $employee = User::lockForUpdate()->findOrFail($id);
        if ($currentOnly && (! $employee->hasRole('employee') || $employee->status === 'Left')) {
            throw ValidationException::withMessages(['employee_id' => 'Choose a current employee.']);
        }

        return $employee;
    }

    private function success(Request $request, string $message, ?string $title = null): JsonResponse|RedirectResponse
    {
        $title ??= match ($request->route()?->getActionMethod()) {
            'store' => 'Right-to-work check saved',
            'destroy' => 'Check removed',
            default => 'Check updated',
        };

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('admin.right-to-work.index')->with('success', $message)->with('toast_title', $title);
    }
}
