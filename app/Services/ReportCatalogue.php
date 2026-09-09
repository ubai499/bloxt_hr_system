<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\AttendanceRecord;
use App\Models\ComplianceReview;
use App\Models\Document;
use App\Models\EmployeeCompensation;
use App\Models\LeaveRequest;
use App\Models\RightToWorkCheck;
use App\Models\SponsorEvent;
use App\Models\SponsorshipRecord;
use App\Models\User;
use App\Models\Vacancy;
use InvalidArgumentException;

class ReportCatalogue
{
    public function __construct(private ComplianceMonitor $monitor) {}

    /**
     * @return list<array{id: string, title: string, icon: string, description: string, columns: list<string>}>
     */
    public function definitions(): array
    {
        return array_map(fn (array $report) => [
            'id' => $report['id'],
            'title' => $report['title'],
            'icon' => $report['icon'],
            'description' => $report['description'],
            'columns' => $report['columns'],
        ], $this->reports());
    }

    public function ids(): array
    {
        return array_column($this->reports(), 'id');
    }

    /**
     * @return array{id: string, title: string, icon: string, description: string, columns: list<string>, rows: list<list<string>>}
     */
    public function run(string $id): array
    {
        foreach ($this->reports() as $report) {
            if ($report['id'] === $id) {
                return [
                    'id' => $report['id'],
                    'title' => $report['title'],
                    'icon' => $report['icon'],
                    'description' => $report['description'],
                    'columns' => $report['columns'],
                    'rows' => $report['rows'](),
                ];
            }
        }

        throw new InvalidArgumentException('Unknown report.');
    }

    /**
     * @return list<array{id: string, title: string, icon: string, description: string, columns: list<string>, rows: callable(): list<list<string>>}>
     */
    private function reports(): array
    {
        return [
            [
                'id' => 'directory', 'title' => 'Employee Directory', 'icon' => 'bi-people',
                'description' => 'All current employee records.',
                'columns' => ['Employee ID', 'Name', 'Job Title', 'Department', 'Status', 'Start Date'],
                'rows' => fn () => $this->employees()->map(fn (User $e) => [$e->employee_number, $e->name, $e->job_title, $e->departmentRecord?->name, $e->status, $this->date($e->start_date)])->values()->all(),
            ],
            [
                'id' => 'current-employees', 'title' => 'Current Employees', 'icon' => 'bi-person-check',
                'description' => 'Employees with an active employment status.',
                'columns' => ['Employee ID', 'Name', 'Job Title', 'Department', 'Employment Type'],
                'rows' => fn () => $this->employees()->where('status', 'Active')->map(fn (User $e) => [$e->employee_number, $e->name, $e->job_title, $e->departmentRecord?->name, $e->employment_type])->values()->all(),
            ],
            [
                'id' => 'contact', 'title' => 'Employee Contact Report', 'icon' => 'bi-telephone',
                'description' => 'Current contact details and last verification date.',
                'columns' => ['Name', 'Mobile', 'Company Email', 'Address', 'Postcode', 'Last Verified'],
                'rows' => fn () => $this->employees()->map(fn (User $e) => [$e->name, $e->phone, $e->email, $e->address, $e->postcode, $this->date($e->contact_verified_date)])->values()->all(),
            ],
            [
                'id' => 'employment-status', 'title' => 'Employment Status Report', 'icon' => 'bi-diagram-2',
                'description' => 'Breakdown of employment status and type across the workforce.',
                'columns' => ['Name', 'Status', 'Employment Type', 'Start Date', 'End Date'],
                'rows' => fn () => $this->employees(false)->map(fn (User $e) => [$e->name, $e->status, $e->employment_type, $this->date($e->start_date), $this->date($e->end_date) ?: 'N/A'])->values()->all(),
            ],
            [
                'id' => 'rtw-status', 'title' => 'Right-to-Work Status', 'icon' => 'bi-patch-check',
                'description' => 'Current right-to-work status for every employee.',
                'columns' => ['Name', 'Nationality', 'Status', 'Permission Expiry'],
                'rows' => fn () => $this->employees()->map(function (User $e) {
                    $check = $e->latestRightToWorkCheck;

                    return [$e->name, $e->nationality, $check?->displayStatus() ?? 'Evidence Missing', $check?->permission_expiry ? $this->date($check->permission_expiry) : 'N/A'];
                })->values()->all(),
            ],
            [
                'id' => 'rtw-review-dates', 'title' => 'Right-to-Work Review Dates', 'icon' => 'bi-calendar-check',
                'description' => 'Employees with a scheduled right-to-work follow-up.',
                'columns' => ['Name', 'Next Review Date', 'Responsible Person'],
                'rows' => fn () => RightToWorkCheck::query()->with('employee')->whereNotNull('next_check_date')->orderBy('next_check_date')->get()
                    ->map(fn (RightToWorkCheck $c) => [$c->employee?->name, $this->date($c->next_check_date), $c->performed_by])->values()->all(),
            ],
            [
                'id' => 'immigration-expiry', 'title' => 'Immigration Expiry Report', 'icon' => 'bi-passport',
                'description' => 'Time-limited immigration permissions and their expiry dates.',
                'columns' => ['Name', 'Immigration Category', 'Permission Expiry'],
                'rows' => fn () => RightToWorkCheck::query()->with('employee')->whereNotNull('permission_expiry')->orderBy('permission_expiry')->get()
                    ->map(fn (RightToWorkCheck $c) => [$c->employee?->name, $c->immigration_category, $this->date($c->permission_expiry)])->values()->all(),
            ],
            [
                'id' => 'sponsored-register', 'title' => 'Sponsored Worker Register', 'icon' => 'bi-shield-check',
                'description' => 'All employees currently on a sponsored worker route.',
                'columns' => ['Name', 'Route', 'SOC Code', 'CoS End Date', 'Status'],
                'rows' => fn () => SponsorshipRecord::query()->with('employee')->orderBy('id')->get()
                    ->map(fn (SponsorshipRecord $s) => [$s->employee?->name, $s->worker_route, $s->soc_code, $this->date($s->cos_end_date), $s->sponsorship_status])->values()->all(),
            ],
            [
                'id' => 'attendance', 'title' => 'Attendance Report', 'icon' => 'bi-calendar-check',
                'description' => 'Attendance records across the workforce.',
                'columns' => ['Name', 'Date', 'Status', 'Location', 'Hours'],
                'rows' => fn () => AttendanceRecord::query()->with('employee')->latest('date')->orderByDesc('id')->get()
                    ->map(fn (AttendanceRecord $a) => [$a->employee?->name, $this->date($a->date), $a->status, $a->work_location, $a->hours])->values()->all(),
            ],
            [
                'id' => 'absence', 'title' => 'Absence Report', 'icon' => 'bi-clipboard-x',
                'description' => 'Recorded absence events and authorisation status.',
                'columns' => ['Name', 'Date', 'Type', 'Authorised', 'Follow-up Required'],
                'rows' => fn () => AbsenceRecord::query()->with('employee')->latest('date')->orderByDesc('id')->get()
                    ->map(fn (AbsenceRecord $a) => [$a->employee?->name, $this->date($a->date), $a->absence_type, $a->authorised ? 'Yes' : 'No', $a->follow_up_required ? 'Yes' : 'No'])->values()->all(),
            ],
            [
                'id' => 'leave', 'title' => 'Leave Report', 'icon' => 'bi-airplane',
                'description' => 'Leave requests and their approval status.',
                'columns' => ['Name', 'Type', 'From', 'To', 'Status'],
                'rows' => fn () => LeaveRequest::query()->with('employee')->latest('from_date')->orderByDesc('id')->get()
                    ->map(fn (LeaveRequest $l) => [$l->employee?->name, $l->leave_type, $this->date($l->from_date), $this->date($l->to_date), $l->status])->values()->all(),
            ],
            [
                'id' => 'documents', 'title' => 'Employee Document Report', 'icon' => 'bi-folder2-open',
                'description' => 'All documents currently on file.',
                'columns' => ['Title', 'Employee', 'Category', 'Status', 'Expiry Date'],
                'rows' => fn () => Document::query()->with('employee')->orderBy('title')->get()
                    ->map(fn (Document $d) => [$d->title, $d->employee?->name ?: 'Company-wide', $d->category, $d->displayStatus(), $d->expiry_date ? $this->date($d->expiry_date) : 'N/A'])->values()->all(),
            ],
            [
                'id' => 'expiring-documents', 'title' => 'Expiring Documents', 'icon' => 'bi-file-earmark-text',
                'description' => 'Documents expiring within 90 days, or already expired.',
                'columns' => ['Title', 'Employee', 'Status', 'Expiry Date'],
                'rows' => fn () => Document::query()->with('employee')->whereNotNull('expiry_date')->get()
                    ->filter(fn (Document $d) => $d->expiry_date->lte(today()->addDays(90)))
                    ->map(fn (Document $d) => [$d->title, $d->employee?->name ?: 'Company-wide', $d->displayStatus(), $this->date($d->expiry_date)])->values()->all(),
            ],
            [
                'id' => 'salary-history', 'title' => 'Salary History', 'icon' => 'bi-cash-stack',
                'description' => 'Historical salary changes across the workforce.',
                'columns' => ['Name', 'Effective Date', 'Salary', 'Reason', 'Approved By'],
                'rows' => fn () => EmployeeCompensation::query()->with('employee')->latest('effective_date')->orderByDesc('id')->get()
                    ->map(fn (EmployeeCompensation $s) => [$s->employee?->name, $this->date($s->effective_date), $s->annual_salary !== null ? '£'.number_format((float) $s->annual_salary, 2) : '', $s->reason, $s->authorised_by])->values()->all(),
            ],
            [
                'id' => 'job-role', 'title' => 'Job Role Report', 'icon' => 'bi-briefcase',
                'description' => 'Current job records and role ownership.',
                'columns' => ['Job Title', 'Department', 'Employment Type', 'Status', 'Approved By'],
                'rows' => fn () => Vacancy::query()->with('department')->orderBy('job_title')->get()
                    ->map(fn (Vacancy $j) => [$j->job_title, $j->department?->name, $j->employment_type, $j->status, $j->hiring_manager])->values()->all(),
            ],
            [
                'id' => 'work-location', 'title' => 'Work Location Report', 'icon' => 'bi-geo-alt',
                'description' => 'Company work locations and assigned headcount.',
                'columns' => ['Site', 'Type', 'Status', 'Employees Assigned'],
                'rows' => fn () => $this->employees()->groupBy(fn (User $e) => $e->work_location ?: 'Not recorded')
                    ->map(fn ($group, $site) => [$site, 'Recorded location', 'Active', (string) $group->count()])->values()->all(),
            ],
            [
                'id' => 'outstanding-actions', 'title' => 'Outstanding Compliance Actions', 'icon' => 'bi-list-check',
                'description' => 'All items currently in the compliance action centre.',
                'columns' => ['Employee', 'Issue', 'Priority', 'Due Date'],
                'rows' => fn () => $this->monitor->actionItems()->map(fn ($item) => [$item['employee'], $item['issue'], $item['priority'], $item['due_date'] ? $this->date($item['due_date']) : '—'])->values()->all(),
            ],
            [
                'id' => 'sponsor-events', 'title' => 'Sponsor Events Register', 'icon' => 'bi-shield-exclamation',
                'description' => 'All logged sponsor duty events.',
                'columns' => ['Worker', 'Event Type', 'Date Occurred', 'Status'],
                'rows' => fn () => SponsorEvent::query()->with('employee')->latest('date_occurred')->orderByDesc('id')->get()
                    ->map(fn (SponsorEvent $e) => [$e->employee?->name, $e->event_type, $this->date($e->date_occurred), $e->status])->values()->all(),
            ],
            [
                'id' => 'internal-review', 'title' => 'Internal Review Report', 'icon' => 'bi-journal-check',
                'description' => 'Internal HR compliance review history.',
                'columns' => ['Review #', 'Date', 'Area', 'Result'],
                'rows' => fn () => ComplianceReview::query()->latest('review_date')->orderByDesc('id')->get()
                    ->map(fn (ComplianceReview $r) => [$r->review_number, $this->date($r->review_date), $r->area, $r->result])->values()->all(),
            ],
        ];
    }

    private function employees(bool $currentOnly = true)
    {
        return User::role('employee')
            ->when($currentOnly, fn ($query) => $query->where('status', '!=', 'Left'))
            ->with(['departmentRecord', 'latestRightToWorkCheck'])
            ->orderBy('name')
            ->get();
    }

    private function date(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return filled($value) ? (string) $value : '';
    }
}
