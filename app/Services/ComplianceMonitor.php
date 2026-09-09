<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ComplianceReview;
use App\Models\Document;
use App\Models\RightToWorkCheck;
use App\Models\SponsorEvent;
use App\Models\SponsorshipRecord;
use App\Models\User;
use Illuminate\Support\Collection;

class ComplianceMonitor
{
    public function metrics(): array
    {
        $items = $this->actionItems();

        return [
            'action_required' => $items->count(),
            'rtw_due' => $this->currentEmployees()->filter(function (User $employee) {
                $status = $employee->latestRightToWorkCheck?->displayStatus();

                return in_array($status, ['Review Due', 'Expiring Soon', 'Follow-up Required', 'Expired'], true);
            })->count(),
            'immigration_near_expiry' => RightToWorkCheck::query()
                ->whereNotNull('permission_expiry')
                ->whereDate('permission_expiry', '>=', today())
                ->whereDate('permission_expiry', '<=', today()->addDays(90))
                ->count(),
            'missing_info' => $this->currentEmployees()->filter(fn (User $employee) => $this->missingInfo($employee) !== [])->count(),
            'unresolved_attendance' => AttendanceRecord::query()->where('manager_reviewed', false)->count(),
            'docs_expiring' => Document::query()->get()->filter(fn (Document $document) => in_array($document->displayStatus(), ['Expiring Soon', 'Expired'], true))->count(),
            'sponsor_events_under_review' => SponsorEvent::query()->where('status', 'Requires Review')->count(),
            'overdue_reviews' => ComplianceReview::query()->whereNotNull('due_date')->whereNull('completion_date')->whereDate('due_date', '<', today())->count(),
        ];
    }

    public function actionItems(): Collection
    {
        $items = collect();

        foreach ($this->currentEmployees() as $employee) {
            $check = $employee->latestRightToWorkCheck;
            if ($check && in_array($check->displayStatus(), ['Review Due', 'Expiring Soon', 'Follow-up Required', 'Expired'], true)) {
                $items->push($this->item($employee, 'Right-to-work follow-up due', in_array($check->displayStatus(), ['Expired', 'Expiring Soon'], true) ? 'High' : 'Medium', $check->permission_expiry?->toDateString() ?? $check->next_check_date?->toDateString(), $check->performed_by, route('admin.right-to-work.index', ['employee' => $employee->id])));
            }

            foreach ($employee->documents as $document) {
                $status = $document->displayStatus();
                if (in_array($status, ['Expiring Soon', 'Expired'], true)) {
                    $items->push($this->item($employee, 'Document approaching expiry '.$document->title, $status === 'Expired' ? 'High' : 'Medium', $document->expiry_date?->toDateString(), null, route('admin.documents.index', ['highlight' => $document->id])));
                }
            }

            $missing = $this->missingInfo($employee);
            if ($missing !== []) {
                $items->push($this->item($employee, 'Employee details incomplete '.implode(', ', $missing), 'Low', null, null, route('admin.employees.show', $employee)));
            }

            foreach ($employee->absenceRecords as $absence) {
                if ($absence->follow_up_required) {
                    $items->push($this->item($employee, 'Unexplained absence requiring review', 'High', $absence->date?->toDateString(), 'Line manager', route('admin.attendance.index', ['tab' => 'absence'])));
                }
            }
        }

        foreach (SponsorEvent::query()->with('employee')->where('status', 'Requires Review')->get() as $event) {
            $employee = $event->employee;
            if (! $employee) {
                continue;
            }
            $items->push($this->item($employee, 'Sponsorship event requires review '.$event->event_type, 'High', $event->reporting_deadline?->toDateString(), $event->assigned_to, route('admin.sponsorship.index', ['tab' => 'events', 'highlight' => $event->id])));
        }

        return $items->sortBy(fn ($item) => [match ($item['priority']) {
            'High' => 0,
            'Medium' => 1,
            default => 2,
        }, $item['due_date'] ?? '9999-99-99'])->values();
    }

    public function calendarEvents(): Collection
    {
        $events = collect();

        foreach (RightToWorkCheck::query()->with('employee')->whereNotNull('next_check_date')->get() as $check) {
            $events->push($this->event($check->next_check_date?->toDateString(), 'Right-to-work follow-up', 'RTW follow-up '.($check->employee?->name ?? ''), 'warning', $check->employee?->id));
        }

        foreach (Document::query()->whereNotNull('expiry_date')->get() as $document) {
            $events->push($this->event($document->expiry_date?->toDateString(), 'Document renewal', $document->title, $document->displayStatus() === 'Expired' ? 'danger' : 'warning', $document->employee_id));
        }

        foreach (User::role('employee')->whereNotNull('end_date')->get() as $employee) {
            $events->push($this->event($employee->end_date?->toDateString(), 'Contract expiry', 'Contract end '.$employee->name, 'danger', $employee->id));
        }

        foreach (User::role('employee')->whereNotNull('probation_end_date')->get() as $employee) {
            $events->push($this->event($employee->probation_end_date?->toDateString(), 'Probation review', 'Probation review '.$employee->name, 'info', $employee->id));
        }

        foreach (SponsorshipRecord::query()->with('employee')->whereNotNull('next_review_date')->get() as $record) {
            $events->push($this->event($record->next_review_date?->toDateString(), 'Sponsorship review', 'Sponsorship review '.($record->employee?->name ?? ''), 'warning', $record->employee_id));
        }

        foreach (ComplianceReview::query()->whereNotNull('due_date')->whereNull('completion_date')->get() as $review) {
            $events->push($this->event($review->due_date?->toDateString(), 'Internal review due', $review->review_number, 'info'));
        }

        return $events->filter(fn ($event) => filled($event['date']))->sortBy('date')->values();
    }

    public function employeeChecklist(User $employee): array
    {
        $docs = $employee->documents;
        $hasDoc = fn (string $category) => $docs->contains(fn (Document $document) => $document->category === $category);
        $sponsored = $employee->currentSponsorship !== null || (bool) $employee->latestRightToWorkCheck?->isSponsored();

        return [
            ['label' => 'Personal details complete', 'complete' => filled($employee->name) && filled($employee->date_of_birth) && filled($employee->nationality)],
            ['label' => 'Current address recorded', 'complete' => filled($employee->address) && filled($employee->postcode)],
            ['label' => 'Contact details current', 'complete' => filled($employee->phone) && filled($employee->contact_verified_date)],
            ['label' => 'Emergency contact recorded', 'complete' => filled($employee->emergency_contact_name) && filled($employee->emergency_contact_phone)],
            ['label' => 'Employment contract on file', 'complete' => $hasDoc('Employment Contract')],
            ['label' => 'Job description on file', 'complete' => $hasDoc('Job Description')],
            ['label' => 'Working hours recorded', 'complete' => $employee->weekly_hours !== null],
            ['label' => 'Work location recorded', 'complete' => filled($employee->work_location)],
            ['label' => 'Salary information recorded', 'complete' => $employee->compensations->isNotEmpty()],
            ['label' => 'Right-to-work record on file', 'complete' => $employee->latestRightToWorkCheck !== null],
            ['label' => 'Attendance records present', 'complete' => $employee->attendanceRecords->isNotEmpty()],
            ['label' => 'Leave record present', 'complete' => $employee->leave_allowance !== null],
            ['label' => 'Sponsor information recorded (if applicable)', 'complete' => ! $sponsored || $employee->currentSponsorship !== null],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingInfo(User $employee): array
    {
        $missing = [];
        if (! filled($employee->address) || ! filled($employee->postcode)) {
            $missing[] = 'Current address';
        }
        if (! filled($employee->emergency_contact_name) || ! filled($employee->emergency_contact_phone)) {
            $missing[] = 'Emergency contact';
        }
        if (! filled($employee->phone)) {
            $missing[] = 'Mobile number';
        }

        return $missing;
    }

    public function workerSummary(User $employee, ?SponsorshipRecord $record = null): array
    {
        $record ??= $employee->currentSponsorship ?? $employee->sponsorshipRecord;
        $rtwStatus = $employee->latestRightToWorkCheck?->displayStatus() ?? 'Evidence Missing';
        $missing = $this->missingInfo($employee);
        $contactCurrent = $employee->contact_verified_date && $employee->contact_verified_date->gte(today()->subYear());
        $overall = 'Current';
        if (! $record || in_array($rtwStatus, ['Expired', 'Evidence Missing'], true) || $missing !== []) {
            $overall = 'Action Required';
        } elseif (in_array($rtwStatus, ['Review Due', 'Expiring Soon', 'Follow-up Required'], true)) {
            $overall = 'Review Required';
        }

        return [
            'rtw_status' => $rtwStatus,
            'contact_current' => (bool) $contactCurrent,
            'missing' => $missing,
            'overall' => $overall,
        ];
    }

    private function currentEmployees()
    {
        return User::role('employee')->where('status', '!=', 'Left')
            ->with(['latestRightToWorkCheck', 'documents', 'absenceRecords', 'compensations', 'attendanceRecords', 'currentSponsorship'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{employee_id: int, employee: string, issue: string, priority: string, due_date: ?string, responsible: ?string, href: string}
     */
    private function item(User $employee, string $issue, string $priority, ?string $dueDate, ?string $responsible, string $href): array
    {
        return [
            'employee_id' => $employee->id,
            'employee' => $employee->name,
            'issue' => $issue,
            'priority' => $priority,
            'due_date' => $dueDate,
            'responsible' => $responsible ?: 'HR Administrator',
            'href' => $href,
        ];
    }

    /**
     * @return array{date: ?string, type: string, label: string, tone: string, employee_id: ?int}
     */
    private function event(?string $date, string $type, string $label, string $tone, ?int $employeeId = null): array
    {
        return ['date' => $date, 'type' => $type, 'label' => $label, 'tone' => $tone, 'employee_id' => $employeeId];
    }
}
