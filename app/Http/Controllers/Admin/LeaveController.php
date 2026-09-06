<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveBalance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function __construct(private LeaveBalance $balances) {}

    public function index(Request $request): View|JsonResponse
    {
        $rows = $this->filteredQuery($request)->with('employee')->latest('from_date')->get();
        $today = today()->toDateString();
        $payload = [
            'rows' => $rows->map(fn ($leave) => $this->row($leave, $request))->values(),
            'stats' => [
                'pending' => $rows->where('status', 'Pending')->count(),
                'approved' => $rows->where('status', 'Approved')->count(),
                'on_leave_today' => today()->isWeekday() ? $rows->filter(fn ($leave) => $leave->status === 'Approved' && $leave->from_date->toDateString() <= $today && $leave->to_date->toDateString() >= $today)->unique('employee_id')->count() : 0,
                'total' => $rows->count(),
            ],
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.leave.index', [
            'payload' => $payload,
            'employees' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(),
            'filterEmployees' => User::whereHas('roles', fn ($query) => $query->where('name', 'employee'))
                ->orWhereHas('leaveRequests')->orderBy('name')->get(),
            'leaveTypes' => LeaveRequest::TYPES,
            'leaveStatuses' => LeaveRequest::STATUSES,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.leave.index', ['new' => 1]);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->load('employee');

        return response()->json($this->row($leaveRequest, $request) + [
            'notes' => $leaveRequest->notes,
            'rejection_reason' => $leaveRequest->rejection_reason,
            'requested_at' => $leaveRequest->requested_at?->format('j M Y'),
            'balances' => collect([$leaveRequest->from_date->year, $leaveRequest->to_date->year])->unique()->values()
                ->map(fn ($year) => $this->balances->balance($leaveRequest->employee, $year)),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->leaveData($request);
        DB::transaction(function () use ($data) {
            $employee = $this->lockEmployee($data['employee_id']);
            $this->balances->validateRequest($employee, $data);
            LeaveRequest::create($data + ['status' => 'Pending', 'requested_at' => now()]);
        });

        return $this->success($request, 'Your leave request has been submitted for approval.');
    }

    public function edit(LeaveRequest $leaveRequest): View
    {
        $this->ensurePending($leaveRequest);

        return view('admin.leave.edit', [
            'leaveRequest' => $leaveRequest,
            'employees' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(),
            'leaveTypes' => LeaveRequest::TYPES,
        ]);
    }

    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse|RedirectResponse
    {
        $data = $this->leaveData($request);
        DB::transaction(function () use ($data, $leaveRequest) {
            User::whereIn('id', [$leaveRequest->employee_id, $data['employee_id']])->orderBy('id')->lockForUpdate()->get();
            $employee = $this->lockEmployee($data['employee_id']);
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leaveRequest->id);
            $this->ensurePending($leave);
            $this->balances->validateRequest($employee, $data, $leave->id);
            $leave->update($data);
        });

        return $this->success($request, 'Leave request updated successfully.');
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected', 'Cancelled'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $leaveRequest, $data) {
            $employee = User::lockForUpdate()->findOrFail($leaveRequest->employee_id);
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leaveRequest->id);
            if ($leave->employee_id !== $employee->id) {
                throw ValidationException::withMessages(['status' => 'This request has changed. Refresh the page and try again.']);
            }
            $this->ensurePending($leave);
            $own = $leave->employee_id === $request->user()->id;
            abort_if($data['status'] === 'Cancelled' ? ! $own : $own, 403, 'You cannot approve or reject your own leave, or cancel another employee\'s request.');
            if ($data['status'] === 'Approved') {
                $this->ensureEmployee($employee);
                $this->balances->validateRequest($employee, [
                    'leave_type' => $leave->leave_type,
                    'from_date' => $leave->from_date->toDateString(),
                    'to_date' => $leave->to_date->toDateString(),
                    'partial_day' => $leave->partial_day,
                ], $leave->id);
            }
            $leave->update([
                'status' => $data['status'],
                'approved_by' => $data['status'] === 'Cancelled' ? null : $request->user()->name,
                'approved_at' => $data['status'] === 'Cancelled' ? null : now(),
                'rejection_reason' => $data['status'] === 'Rejected' ? ($data['rejection_reason'] ?? null) : null,
            ]);
        });

        return $this->success($request, 'The leave request has been '.strtolower($data['status']).'.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($leaveRequest) {
            User::whereKey($leaveRequest->employee_id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leaveRequest->id);
            $this->ensurePending($leave);
            $leave->delete();
        });

        return $this->success($request, 'Leave request deleted successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        $sorting = $request->validate([
            'sort' => ['nullable', Rule::in(['employee', 'leave_type', 'from_date', 'to_date', 'reason', 'status', 'approved_by'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $rows = $this->filteredQuery($request)->with('employee')->latest('from_date')->get();
        $sort = $sorting['sort'] ?? 'from_date';
        $rows = $rows->sortBy(fn ($leave) => match ($sort) {
            'employee' => $leave->employee->name,
            'from_date', 'to_date' => $leave->{$sort}->toDateString(),
            default => $leave->{$sort} ?? '',
        }, SORT_NATURAL | SORT_FLAG_CASE, ($sorting['direction'] ?? 'desc') === 'desc');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Employee', 'Type', 'From', 'To', 'Reason', 'Status', 'Approved By']);
            foreach ($rows as $leave) {
                $cells = [$leave->employee->name, $leave->leave_type, $leave->from_date->toDateString(), $leave->to_date->toDateString(), $leave->reason ?? '', $leave->status, $leave->approved_by ?? ''];
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', $cell) ? "'".$cell : $cell, $cells));
            }
            fclose($stream);
        }, 'leave-requests.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(LeaveRequest::STATUSES)],
            'type' => ['nullable', Rule::in(LeaveRequest::TYPES)],
            'employee' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return LeaveRequest::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('leave_type', $type))
            ->when($filters['employee'] ?? null, fn ($query, $employee) => $query->where('employee_id', $employee));
    }

    private function row(LeaveRequest $leave, Request $request): array
    {
        return [
            'id' => $leave->id,
            'employee' => $leave->employee->name,
            'leave_type' => $leave->leave_type,
            'from_date' => $leave->from_date->toDateString(),
            'to_date' => $leave->to_date->toDateString(),
            'reason' => $leave->reason,
            'status' => $leave->status,
            'approved_by' => $leave->approved_by,
            'partial_day' => $leave->partial_day,
            'duration' => $this->balances->workingDays($leave->from_date->toDateString(), $leave->to_date->toDateString(), $leave->partial_day),
            'can_approve' => $leave->status === 'Pending' && $leave->employee_id !== $request->user()->id,
            'can_cancel' => $leave->status === 'Pending' && $leave->employee_id === $request->user()->id,
            'details_url' => route('admin.leave.show', $leave),
            'status_url' => route('admin.leave.status.update', $leave),
        ];
    }

    private function leaveData(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'leave_type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'partial_day' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['to_date.after_or_equal' => 'End date must be on or after the start date.']);
        $data['partial_day'] = $request->boolean('partial_day');

        return $data;
    }

    private function lockEmployee(int $id): User
    {
        $employee = User::lockForUpdate()->findOrFail($id);
        $this->ensureEmployee($employee);

        return $employee;
    }

    private function ensureEmployee(User $employee): void
    {
        if (! $employee->hasRole('employee') || $employee->status === 'Left') {
            throw ValidationException::withMessages(['employee_id' => 'Choose a current employee.']);
        }
    }

    private function ensurePending(LeaveRequest $leave): void
    {
        if ($leave->status !== 'Pending') {
            throw ValidationException::withMessages(['status' => 'This request has already been decided. Refresh the page to see its current status.']);
        }
    }

    private function success(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message])
            : redirect()->route('admin.leave.index')->with('success', $message)->with('toast_title', match ($request->route()->getActionMethod()) {
                'store' => 'Leave requested',
                'destroy' => 'Leave deleted',
                'updateStatus' => 'Leave '.strtolower($request->input('status')),
                default => 'Leave updated',
            });
    }
}
