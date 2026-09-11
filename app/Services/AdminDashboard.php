<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Document;
use App\Models\HrTask;
use App\Models\LeaveRequest;
use App\Models\User;

class AdminDashboard
{
    public function __construct(private ComplianceMonitor $monitor) {}

    public function payload(): array
    {
        $employees = User::role('employee')->where('status', '!=', 'Left')
            ->with(['departmentRecord', 'latestRightToWorkCheck', 'currentSponsorship'])
            ->orderBy('name')
            ->get();
        $active = $employees->where('status', 'Active');
        $otherStatus = $employees->count() - $active->count();
        $todayAttendance = AttendanceRecord::query()->whereDate('date', today())->get();
        $absentToday = $todayAttendance->whereIn('status', ['Sick', 'Authorised Absence', 'Unauthorised Absence'])->count();
        $onLeaveToday = LeaveRequest::query()->where('status', 'Approved')
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->count();
        $expiringDocs = Document::query()->get()->filter(fn (Document $document) => in_array($document->displayStatus(), ['Expiring Soon', 'Expired'], true));
        $docsIn30 = Document::query()->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(30))
            ->count();
        $rtwDue = $employees->filter(function (User $employee) {
            return in_array($employee->latestRightToWorkCheck?->displayStatus(), ['Review Due', 'Expiring Soon', 'Follow-up Required', 'Expired'], true);
        });
        $rtwDue30 = $rtwDue->filter(function (User $employee) {
            $expiry = $employee->latestRightToWorkCheck?->permission_expiry;

            return $expiry && $expiry->gte(today()) && $expiry->lte(today()->addDays(30));
        })->count();
        $sponsored = $employees->filter(fn (User $employee) => $employee->currentSponsorship !== null)->count();
        $actions = $this->monitor->actionItems();
        $attendanceCounts = $todayAttendance->countBy('status');
        $notRecorded = max(0, $employees->count() - $todayAttendance->pluck('employee_id')->unique()->count());
        if ($notRecorded > 0) {
            $attendanceCounts = $attendanceCounts->put('Not yet recorded', $notRecorded);
        }

        return [
            'metrics' => [
                ['label' => 'Total Employees', 'value' => $employees->count(), 'context' => 'Across '.Department::active()->count().' departments', 'accent' => 'primary', 'icon' => 'bi-people'],
                ['label' => 'Active Employees', 'value' => $active->count(), 'context' => $otherStatus ? $otherStatus.' on leave or probation' : 'All current employees are active', 'accent' => 'success', 'icon' => 'bi-person-check'],
                ['label' => 'Absent Today', 'value' => $absentToday, 'context' => $absentToday ? 'Requires manager review' : 'No unexplained absence', 'accent' => $absentToday ? 'danger' : 'neutral', 'icon' => 'bi-clipboard-x'],
                ['label' => 'On Leave Today', 'value' => $onLeaveToday, 'context' => 'Approved leave in progress', 'accent' => 'info', 'icon' => 'bi-airplane'],
                ['label' => 'Documents Expiring Soon', 'value' => $expiringDocs->count(), 'context' => $docsIn30.' within 30 days', 'accent' => $expiringDocs->isNotEmpty() ? 'warning' : 'neutral', 'icon' => 'bi-file-earmark-text'],
                ['label' => 'Right-to-Work Checks Due', 'value' => $rtwDue->count(), 'context' => $rtwDue30.' within 30 days', 'accent' => $rtwDue->isNotEmpty() ? 'warning' : 'neutral', 'icon' => 'bi-patch-check'],
                ['label' => 'Sponsored Workers', 'value' => $sponsored, 'context' => 'Current sponsored worker records', 'accent' => 'accent', 'icon' => 'bi-shield-check'],
                ['label' => 'Outstanding HR Actions', 'value' => $actions->count(), 'context' => $actions->isNotEmpty() ? 'See action centre below' : 'Nothing outstanding', 'accent' => $actions->isNotEmpty() ? 'danger' : 'neutral', 'icon' => 'bi-list-check'],
            ],
            'actions' => $actions->take(6)->values(),
            'open_tasks' => HrTask::query()->whereNotIn('status', ['Completed', 'Cancelled'])->count(),
            'upcoming' => $this->monitor->calendarEvents()
                ->filter(fn ($event) => filled($event['date']) && $event['date'] >= today()->toDateString())
                ->take(5)
                ->values(),
            'activity' => AuditEvent::query()->with('employee')->latest('occurred_at')->latest('id')->limit(6)->get()->map(fn (AuditEvent $event) => [
                'date' => $event->occurred_at?->toIso8601String(),
                'title' => trim($event->action.($event->employee?->name ? ' '.$event->employee->name : '')),
                'by' => $event->user_name ?: 'System',
            ])->values(),
            'charts' => [
                'departments' => [
                    'labels' => $employees->groupBy(fn (User $employee) => $employee->departmentRecord?->name ?: 'Unassigned')->map->count()->keys()->values(),
                    'data' => $employees->groupBy(fn (User $employee) => $employee->departmentRecord?->name ?: 'Unassigned')->map->count()->values(),
                ],
                'attendance' => [
                    'labels' => $attendanceCounts->keys()->values(),
                    'data' => $attendanceCounts->values()->values(),
                ],
            ],
        ];
    }
}
