<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRecord;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AbsenceWorkflow;
use App\Services\AttendanceAlerts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    private const ATTENDANCE_STATUSES = ['Present', 'Remote', 'Office', 'Approved Leave', 'Sick', 'Authorised Absence', 'Unpaid Leave', 'Business Travel', 'Training', 'Unauthorised Absence', 'Other'];

    public function __construct(private AbsenceWorkflow $absences, private AttendanceAlerts $alerts) {}

    public function index(Request $request): View|JsonResponse
    {
        $payload = [
            'attendance' => AttendanceRecord::with('employee')->latest('date')->get()->map(fn ($record) => $this->attendanceRow($record)),
            'absences' => AbsenceRecord::with('employee')->latest('date')->get()->map(fn ($absence) => $this->absenceRow($absence)),
            'alerts' => $this->alerts->all(),
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.attendance.index', [
            'payload' => $payload, 'employees' => $this->employees(), 'statuses' => self::ATTENDANCE_STATUSES,
            'filterEmployees' => User::role('employee')->orderBy('name')->get(),
            'absenceTypes' => AbsenceRecord::TYPES,
            'activeTab' => $request->query('tab') === 'absence' ? 'absence' : 'attendance',
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.attendance.index', ['new' => 1]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->saveAttendance($this->attendanceData($request));

        return $this->success($request, 'The attendance record has been added.');
    }

    public function edit(AttendanceRecord $record): View
    {
        return view('admin.attendance.edit', ['record' => $record, 'employees' => $this->employees(), 'statuses' => self::ATTENDANCE_STATUSES]);
    }

    public function update(Request $request, AttendanceRecord $record): JsonResponse|RedirectResponse
    {
        $this->saveAttendance($this->attendanceData($request), $record);

        return $this->success($request, 'Attendance record updated successfully.');
    }

    public function review(Request $request, AttendanceRecord $record): JsonResponse|RedirectResponse
    {
        $record->update(['manager_reviewed' => true, 'reviewed_by' => $request->user()->name, 'reviewed_at' => now()]);

        return $this->success($request, 'Attendance record marked as reviewed.');
    }

    public function destroy(AttendanceRecord $record): RedirectResponse
    {
        DB::transaction(function () use ($record) {
            User::whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
            if (AbsenceRecord::where('attendance_record_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['date' => 'This attendance belongs to an absence. Review the absence record instead.']);
            }
            $record->delete();
        });

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance record deleted successfully.')->with('toast_title', 'Attendance deleted');
    }

    public function createAbsence(Request $request): RedirectResponse
    {
        return redirect()->route('admin.attendance.index', array_filter(['tab' => 'absence', 'new' => 1, 'employee' => $request->query('employee')]));
    }

    public function storeAbsence(Request $request): JsonResponse|RedirectResponse
    {
        $this->absences->save($this->absenceData($request), $request->user());

        return $this->success($request, 'The absence record has been saved.', 'absence');
    }

    public function showAbsence(AbsenceRecord $absence): JsonResponse
    {
        return response()->json($this->absenceRow($absence->load('employee')) + [
            'manager_notes' => $absence->manager_notes,
            'reported_to' => $absence->reported_to,
            'actual_return' => $absence->actual_return?->toDateString(),
            'reviewed_by' => $absence->reviewed_by,
            'reviewed_at' => $absence->reviewed_at?->format('j M Y H:i'),
        ]);
    }

    public function editAbsence(AbsenceRecord $absence): RedirectResponse
    {
        return redirect()->route('admin.attendance.index', ['tab' => 'absence', 'highlight' => $absence->id, 'review' => 1]);
    }

    public function updateAbsence(Request $request, AbsenceRecord $absence): JsonResponse|RedirectResponse
    {
        $this->absences->save($this->absenceData($request), $request->user(), $absence);

        return $this->success($request, 'The absence review has been saved.', 'absence');
    }

    public function destroyAbsence(AbsenceRecord $absence): RedirectResponse
    {
        $this->absences->delete($absence);

        return redirect()->route('admin.attendance.index', ['tab' => 'absence'])->with('success', 'Absence record deleted successfully.')->with('toast_title', 'Absence deleted');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'], 'employee' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(self::ATTENDANCE_STATUSES)],
            'sort' => ['nullable', Rule::in(['employee', 'date', 'expected_start', 'clock_in', 'clock_out', 'hours', 'work_location', 'status', 'notes'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $rows = AttendanceRecord::with('employee')
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('date', $date))
            ->when($filters['employee'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->get()
            ->map(fn ($record) => $this->attendanceRow($record))
            ->sortBy($filters['sort'] ?? 'date', SORT_REGULAR, ($filters['direction'] ?? 'desc') === 'desc');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Employee', 'Date', 'Expected Start', 'Clock In', 'Clock Out', 'Hours', 'Location', 'Status', 'Notes']);
            foreach ($rows as $row) {
                $cells = array_map(fn ($key) => (string) ($row[$key] ?? ''), ['employee', 'date', 'expected_start', 'clock_in', 'clock_out', 'hours', 'work_location', 'status', 'notes']);
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', $cell) ? "'".$cell : $cell, $cells));
            }
            fclose($stream);
        }, 'attendance-records.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function saveAttendance(array $data, ?AttendanceRecord $record = null): void
    {
        DB::transaction(function () use ($data, $record) {
            User::whereIn('id', array_filter([$data['employee_id'], $record?->employee_id]))->orderBy('id')->lockForUpdate()->get();
            $this->ensureEmployee($data['employee_id']);
            if (AttendanceRecord::where('employee_id', $data['employee_id'])->whereDate('date', $data['date'])
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                throw ValidationException::withMessages(['date' => 'An attendance record already exists for this employee on the selected date.']);
            }
            $linked = $record ? AbsenceRecord::where('attendance_record_id', $record->id)->first() : null;
            $absence = AbsenceRecord::where('employee_id', $data['employee_id'])->whereDate('date', $data['date'])->first();
            if ($linked && ($linked->employee_id != $data['employee_id'] || $linked->date->toDateString() !== $data['date'])) {
                throw ValidationException::withMessages(['date' => 'Change the employee or date through the linked absence record.']);
            }
            if ($absence && ! $this->absences->compatible(new AttendanceRecord($data), $absence->absence_type)) {
                throw ValidationException::withMessages(['status' => 'This conflicts with a recorded absence. Review the absence record instead.']);
            }
            $record ??= new AttendanceRecord;
            $record->fill($data)->save();
            if ($absence && ! $absence->attendance_record_id) {
                $absence->update(['attendance_record_id' => $record->id, 'attendance_created' => false]);
            }
        });
    }

    private function attendanceData(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'], 'date' => ['required', 'date_format:Y-m-d'],
            'expected_start' => ['nullable', 'date_format:H:i'], 'clock_in' => ['nullable', 'date_format:H:i'], 'clock_out' => ['nullable', 'date_format:H:i'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'], 'work_location' => ['nullable', Rule::in(['Office', 'Remote', 'Client Site', 'N/A'])],
            'status' => ['required', Rule::in(self::ATTENDANCE_STATUSES)], 'notes' => ['nullable', 'string', 'max:5000'], 'manager_reviewed' => ['nullable', 'boolean'],
        ]);
        $reviewed = $request->boolean('manager_reviewed');
        $data['hours'] = $data['hours'] ?? 0;
        $data['manager_reviewed'] = $reviewed;

        return $data + ['reviewed_by' => $reviewed ? $request->user()->name : null, 'reviewed_at' => $reviewed ? now() : null];
    }

    private function absenceData(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'], 'date' => ['required', 'date_format:Y-m-d'],
            'absence_type' => ['required', Rule::in(AbsenceRecord::TYPES)], 'reason' => ['nullable', 'string', 'max:255'],
            'reported_date' => ['nullable', 'required_with:how_reported,reported_to', 'date_format:Y-m-d', 'before_or_equal:today'],
            'how_reported' => ['nullable', 'string', 'max:255'], 'reported_to' => ['nullable', 'string', 'max:255'],
            'expected_return' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'actual_return' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date', 'before_or_equal:today'],
            'manager_notes' => ['nullable', 'string', 'max:5000'], 'authorised' => ['nullable', 'boolean'], 'follow_up_required' => ['nullable', 'boolean'],
        ]);
        $data['authorised'] = $request->boolean('authorised');
        $data['follow_up_required'] = $request->boolean('follow_up_required');
        if (($data['absence_type'] === 'Authorised Absence' && ! $data['authorised']) || ($data['absence_type'] === 'Unauthorised Absence' && $data['authorised'])) {
            throw ValidationException::withMessages(['authorised' => 'Authorisation must agree with the selected absence type.']);
        }

        return $data;
    }

    private function attendanceRow(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id, 'employee_id' => $record->employee_id, 'employee' => $record->employee->name,
            'date' => $record->date->toDateString(), 'expected_start' => $record->expected_start ? substr($record->expected_start, 0, 5) : null,
            'clock_in' => $record->clock_in ? substr($record->clock_in, 0, 5) : null, 'clock_out' => $record->clock_out ? substr($record->clock_out, 0, 5) : null,
            'hours' => (float) $record->hours, 'work_location' => $record->work_location, 'status' => $record->status, 'notes' => $record->notes,
            'manager_reviewed' => $record->manager_reviewed, 'review_url' => route('admin.attendance.review', $record),
        ];
    }

    private function absenceRow(AbsenceRecord $absence): array
    {
        return [
            'id' => $absence->id, 'employee_id' => $absence->employee_id, 'employee' => $absence->employee->name,
            'date' => $absence->date->toDateString(), 'absence_type' => $absence->absence_type, 'reason' => $absence->reason,
            'reported_date' => $absence->reported_date?->toDateString(), 'how_reported' => $absence->how_reported,
            'expected_return' => $absence->expected_return?->toDateString(), 'authorised' => $absence->authorised,
            'follow_up_required' => $absence->follow_up_required,
            'details_url' => route('admin.attendance.absence.show', $absence), 'update_url' => route('admin.attendance.absence.update', $absence),
        ];
    }

    private function employees()
    {
        return User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get();
    }

    private function ensureEmployee(int $id): void
    {
        $employee = User::find($id);
        if (! $employee?->hasRole('employee') || $employee->status === 'Left') {
            throw ValidationException::withMessages(['employee_id' => 'Choose a current employee.']);
        }
    }

    private function success(Request $request, string $message, string $tab = 'attendance'): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message])
            : redirect()->route('admin.attendance.index', $tab === 'absence' ? ['tab' => 'absence'] : [])->with('success', $message)->with('toast_title', match ($request->route()->getActionMethod()) {
                'storeAbsence' => 'Absence recorded',
                'updateAbsence' => 'Absence reviewed',
                'review' => 'Marked reviewed',
                default => 'Attendance saved',
            });
    }
}
