<?php

namespace App\Services;

use App\Models\User;

class AttendanceAlerts
{
    public function all(): array
    {
        $alerts = [];
        $employees = User::role('employee')->where('status', 'Active')->with([
            'latestRightToWorkCheck',
            'attendanceRecords' => fn ($q) => $q->whereDate('date', '<=', today())->orderByDesc('date'),
            'absenceRecords' => fn ($q) => $q->where('follow_up_required', true)->orderByDesc('date'),
        ])->orderBy('name')->get();
        foreach ($employees as $employee) {
            $records = $employee->attendanceRecords;
            $pending = $records->where('manager_reviewed', false);
            $unexplained = $pending->where('status', 'Unauthorised Absence');
            $add = function (string $type, string $tone, $date = null, ?int $absenceId = null, ?int $attendanceId = null) use (&$alerts, $employee) {
                $alerts[] = [
                    'type' => $type, 'tone' => $tone, 'employee' => $employee->name, 'employee_id' => $employee->id,
                    'date' => $date?->toDateString(), 'absence_id' => $absenceId, 'attendance_id' => $attendanceId,
                ];
            };
            if ($records->isEmpty()) {
                $add('No attendance recorded', 'info');
            }
            if ($unexplained->isNotEmpty()) {
                $latest = $unexplained->first();
                $absence = $employee->absenceRecords->first(fn ($a) => $a->date->isSameDay($latest->date));
                $add($unexplained->count() > 1 ? 'Repeated unexplained absence' : 'Employee has missed expected working day',
                    $unexplained->count() > 1 ? 'danger' : 'warning', $latest->date, $absence?->id, $latest->id);
            }
            foreach ($employee->absenceRecords as $absence) {
                $sponsored = $employee->latestRightToWorkCheck?->isSponsored();
                // Avoid duplicating the same unsponsored absence warning above.
                if (! $sponsored && $unexplained->first()?->date->isSameDay($absence->date)) {
                    continue;
                }
                $add($sponsored ? 'Sponsored worker has an unresolved absence record' : 'Absence record requires follow-up', 'warning', $absence->date, $absence->id);
            }
            if ($pending->isNotEmpty()) {
                $add('Attendance information awaiting manager confirmation', 'info', $pending->first()->date, null, $pending->first()->id);
            }
        }

        return $alerts;
    }
}
