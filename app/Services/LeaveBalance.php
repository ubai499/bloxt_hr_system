<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LeaveBalance
{
    // The prototype uses Monday-Friday working days and a calendar-year allowance.
    public function workingDays(string $from, string $to, bool $partial = false): float
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        if ($end->lt($start)) {
            return 0;
        }
        $days = (int) $start->diffInDays($end) + 1;
        $count = intdiv($days, 7) * 5;
        for ($i = 0; $i < $days % 7; $i++) {
            if ($start->addDays($i)->isWeekday()) {
                $count++;
            }
        }

        return $partial ? $count * 0.5 : $count;
    }

    public function balance(User $employee, int $year): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->toDateString();
        $end = CarbonImmutable::create($year, 12, 31)->toDateString();
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        $taken = $booked = 0;
        foreach ($this->annualRequests($employee, $start, $end, ['Approved'])->get() as $leave) {
            $from = max($start, $leave->from_date->toDateString());
            $to = min($end, $leave->to_date->toDateString());
            $taken += $this->workingDays($from, min($to, $yesterday), $leave->partial_day);
            $booked += $this->workingDays(max($from, $today), $to, $leave->partial_day);
        }
        $allowance = (float) $employee->leave_allowance;

        return ['year' => $year, 'allowance' => $allowance, 'taken' => $taken, 'booked' => $booked, 'remaining' => $allowance - $taken - $booked];
    }

    /** Call while holding the employee row lock, including when approving. */
    public function validateRequest(User $employee, array $data, ?int $ignoreId = null): void
    {
        if ($data['partial_day'] && $data['from_date'] !== $data['to_date']) {
            throw ValidationException::withMessages(['to_date' => 'Partial-day leave must start and end on the same date.']);
        }
        if ($this->workingDays($data['from_date'], $data['to_date'], $data['partial_day']) === 0.0) {
            throw ValidationException::withMessages(['from_date' => 'Choose a date range containing a working day (Monday to Friday).']);
        }
        $overlap = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['Pending', 'Approved'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('from_date', '<=', $data['to_date'])->whereDate('to_date', '>=', $data['from_date'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['from_date' => 'This employee already has pending or approved leave overlapping these dates.']);
        }
        if (AbsenceRecord::where('employee_id', $employee->id)->whereDate('date', '>=', $data['from_date'])->whereDate('date', '<=', $data['to_date'])->exists()) {
            throw ValidationException::withMessages(['from_date' => 'This employee has a recorded absence during these dates. Reconcile the absence before requesting or approving leave.']);
        }
        if ($data['leave_type'] !== 'Annual leave') {
            return;
        }
        $firstYear = (int) substr($data['from_date'], 0, 4);
        $lastYear = (int) substr($data['to_date'], 0, 4);
        for ($year = $firstYear; $year <= $lastYear; $year++) {
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-12-31', $year);
            // Pending requests reserve allowance, preventing simultaneous overbooking.
            $reserved = $this->annualRequests($employee, $start, $end, ['Pending', 'Approved'])
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->get()
                ->sum(fn ($leave) => $this->workingDays(max($start, $leave->from_date->toDateString()), min($end, $leave->to_date->toDateString()), $leave->partial_day));
            $requested = $this->workingDays(max($start, $data['from_date']), min($end, $data['to_date']), $data['partial_day']);
            if ($reserved + $requested > (float) $employee->leave_allowance) {
                throw ValidationException::withMessages(['to_date' => "This request exceeds the employee's available annual leave for {$year}, including pending requests."]);
            }
        }
    }

    private function annualRequests(User $employee, string $start, string $end, array $statuses)
    {
        return LeaveRequest::where('employee_id', $employee->id)->where('leave_type', 'Annual leave')
            ->whereIn('status', $statuses)->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start);
    }
}
