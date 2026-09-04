<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    private const LEAVE_TYPES = ['Annual leave', 'Sick leave', 'Unpaid leave', 'Compassionate leave', 'Parental leave', 'Other leave'];

    private const LEAVE_STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

    public function index(Request $request): View
    {
        $leaveRequests = LeaveRequest::query()
            ->with('employee')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('type'), fn ($query) => $query->where('leave_type', $request->type))
            ->when($request->filled('employee'), fn ($query) => $query->where('employee_id', $request->employee))
            ->latest('from_date')
            ->paginate(12)
            ->withQueryString();

        return view('admin.leave.index', [
            'leaveRequests' => $leaveRequests,
            'employees' => $this->employees(),
            'leaveTypes' => self::LEAVE_TYPES,
            'leaveStatuses' => self::LEAVE_STATUSES,
            'stats' => [
                'pending' => LeaveRequest::where('status', 'Pending')->count(),
                'approved' => LeaveRequest::where('status', 'Approved')->count(),
                'on_leave_today' => LeaveRequest::where('status', 'Approved')->whereDate('from_date', '<=', today())->whereDate('to_date', '>=', today())->count(),
                'total' => LeaveRequest::count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.leave.create', [
            'leaveRequest' => new LeaveRequest([
                'from_date' => today(),
                'to_date' => today(),
            ]),
            'employees' => $this->employees(),
            'leaveTypes' => self::LEAVE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LeaveRequest::create($this->leaveData($request) + [
            'status' => 'Pending',
            'requested_at' => now(),
        ]);

        return redirect()->route('admin.leave.index')->with('success', 'Leave request submitted successfully.');
    }

    public function edit(LeaveRequest $leaveRequest): View
    {
        return view('admin.leave.edit', [
            'leaveRequest' => $leaveRequest,
            'employees' => $this->employees(),
            'leaveTypes' => self::LEAVE_TYPES,
        ]);
    }

    public function update(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $leaveRequest->update($this->leaveData($request));

        return redirect()->route('admin.leave.index')->with('success', 'Leave request updated successfully.');
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->status === 'Pending', 422);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected', 'Cancelled'])],
        ]);

        $leaveRequest->update([
            'status' => $validated['status'],
            'approved_by' => $validated['status'] === 'Cancelled' ? null : $request->user()->name,
            'approved_at' => $validated['status'] === 'Cancelled' ? null : now(),
        ]);

        return back()->with('success', 'Leave request '.$validated['status'].'.');
    }

    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        $leaveRequest->delete();

        return redirect()->route('admin.leave.index')->with('success', 'Leave request deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveData(Request $request): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:users,id'],
            'leave_type' => ['required', Rule::in(self::LEAVE_TYPES)],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'partial_day' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureEmployee($validated['employee_id']);

        return [
            'employee_id' => $validated['employee_id'],
            'leave_type' => $validated['leave_type'],
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'partial_day' => $request->boolean('partial_day'),
            'reason' => filled($validated['reason'] ?? null) ? trim($validated['reason']) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ];
    }

    private function employees()
    {
        return User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get();
    }

    private function ensureEmployee(int $employeeId): void
    {
        abort_unless(User::find($employeeId)?->hasRole('employee'), 422);
    }
}
