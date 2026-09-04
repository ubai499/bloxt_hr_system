<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRecord;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private const ATTENDANCE_STATUSES = [
        'Present', 'Remote', 'Office', 'Approved Leave', 'Sick', 'Authorised Absence',
        'Unpaid Leave', 'Business Travel', 'Training', 'Unauthorised Absence', 'Other',
    ];

    private const ABSENCE_TYPES = ['Sick', 'Authorised Absence', 'Unauthorised Absence', 'Other'];

    public function index(Request $request): View
    {
        $attendance = AttendanceRecord::query()
            ->with('employee')
            ->when($request->filled('date'), fn ($query) => $query->whereDate('date', $request->date))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('employee'), fn ($query) => $query->where('employee_id', $request->employee))
            ->latest('date')
            ->paginate(12, ['*'], 'attendance_page')
            ->withQueryString();

        $absences = AbsenceRecord::query()
            ->with('employee')
            ->latest('date')
            ->paginate(12, ['*'], 'absence_page')
            ->withQueryString();

        return view('admin.attendance.index', [
            'attendance' => $attendance,
            'absences' => $absences,
            'employees' => $this->employees(),
            'statuses' => self::ATTENDANCE_STATUSES,
            'activeTab' => $request->query('tab') === 'absence' ? 'absence' : 'attendance',
            'pendingReviews' => AttendanceRecord::query()->with('employee')->where('manager_reviewed', false)->latest('date')->take(5)->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.attendance.create', [
            'record' => new AttendanceRecord([
                'date' => today(),
                'expected_start' => '09:00',
                'hours' => 8,
                'work_location' => 'Office',
                'status' => 'Present',
                'manager_reviewed' => true,
            ]),
            'employees' => $this->employees(),
            'statuses' => self::ATTENDANCE_STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AttendanceRecord::create($this->attendanceData($request));

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance record saved successfully.');
    }

    public function edit(AttendanceRecord $record): View
    {
        return view('admin.attendance.edit', [
            'record' => $record,
            'employees' => $this->employees(),
            'statuses' => self::ATTENDANCE_STATUSES,
        ]);
    }

    public function update(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $record->update($this->attendanceData($request, $record));

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance record updated successfully.');
    }

    public function review(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $record->update([
            'manager_reviewed' => true,
            'reviewed_by' => $request->user()->name,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Attendance record marked as reviewed.');
    }

    public function destroy(AttendanceRecord $record): RedirectResponse
    {
        $record->delete();

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance record deleted successfully.');
    }

    public function createAbsence(): View
    {
        return view('admin.attendance.create-absence', [
            'absence' => new AbsenceRecord([
                'date' => today(),
                'reported_date' => today(),
            ]),
            'employees' => $this->employees(),
            'absenceTypes' => self::ABSENCE_TYPES,
        ]);
    }

    public function storeAbsence(Request $request): RedirectResponse
    {
        AbsenceRecord::create($this->absenceData($request));

        return redirect()->route('admin.attendance.index', ['tab' => 'absence'])->with('success', 'Absence record saved successfully.');
    }

    public function editAbsence(AbsenceRecord $absence): View
    {
        return view('admin.attendance.edit-absence', [
            'absence' => $absence,
            'employees' => $this->employees(),
            'absenceTypes' => self::ABSENCE_TYPES,
        ]);
    }

    public function updateAbsence(Request $request, AbsenceRecord $absence): RedirectResponse
    {
        $absence->update($this->absenceData($request));

        return redirect()->route('admin.attendance.index', ['tab' => 'absence'])->with('success', 'Absence record updated successfully.');
    }

    public function destroyAbsence(AbsenceRecord $absence): RedirectResponse
    {
        $absence->delete();

        return redirect()->route('admin.attendance.index', ['tab' => 'absence'])->with('success', 'Absence record deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceData(Request $request, ?AttendanceRecord $record = null): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:users,id', Rule::unique('attendance_records')->ignore($record)->where('date', $request->date)],
            'date' => ['required', 'date'],
            'expected_start' => ['nullable', 'date_format:H:i'],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'work_location' => ['nullable', Rule::in(['Office', 'Remote', 'Client Site', 'N/A'])],
            'status' => ['required', Rule::in(self::ATTENDANCE_STATUSES)],
            'notes' => ['nullable', 'string'],
            'manager_reviewed' => ['nullable', 'boolean'],
        ]);

        $this->ensureEmployee($validated['employee_id']);

        return [
            'employee_id' => $validated['employee_id'],
            'date' => $validated['date'],
            'expected_start' => $validated['expected_start'] ?? null,
            'clock_in' => $validated['clock_in'] ?? null,
            'clock_out' => $validated['clock_out'] ?? null,
            'hours' => $validated['hours'] ?? 0,
            'work_location' => $validated['work_location'] ?? null,
            'status' => $validated['status'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'manager_reviewed' => $request->boolean('manager_reviewed'),
            'reviewed_by' => $request->boolean('manager_reviewed') ? $request->user()->name : null,
            'reviewed_at' => $request->boolean('manager_reviewed') ? now() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function absenceData(Request $request): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'absence_type' => ['required', Rule::in(self::ABSENCE_TYPES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'reported_date' => ['nullable', 'date'],
            'how_reported' => ['nullable', 'string', 'max:255'],
            'reported_to' => ['nullable', 'string', 'max:255'],
            'expected_return' => ['nullable', 'date'],
            'manager_notes' => ['nullable', 'string'],
            'authorised' => ['nullable', 'boolean'],
            'follow_up_required' => ['nullable', 'boolean'],
        ]);

        $this->ensureEmployee($validated['employee_id']);

        return [
            'employee_id' => $validated['employee_id'],
            'date' => $validated['date'],
            'absence_type' => $validated['absence_type'],
            'reason' => filled($validated['reason'] ?? null) ? trim($validated['reason']) : null,
            'reported_date' => $validated['reported_date'] ?? null,
            'how_reported' => filled($validated['how_reported'] ?? null) ? trim($validated['how_reported']) : null,
            'reported_to' => filled($validated['reported_to'] ?? null) ? trim($validated['reported_to']) : null,
            'expected_return' => $validated['expected_return'] ?? null,
            'manager_notes' => filled($validated['manager_notes'] ?? null) ? trim($validated['manager_notes']) : null,
            'authorised' => $request->boolean('authorised'),
            'follow_up_required' => $request->boolean('follow_up_required'),
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
