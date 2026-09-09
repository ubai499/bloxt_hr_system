<?php

namespace App\Observers;

use App\Models\AbsenceRecord;
use App\Models\AttendanceRecord;
use App\Models\Candidate;
use App\Models\CompanyChange;
use App\Models\ComplianceReview;
use App\Models\Department;
use App\Models\Document;
use App\Models\EmployeeCompensation;
use App\Models\HrTask;
use App\Models\LeaveRequest;
use App\Models\PayrollRecord;
use App\Models\RightToWorkCheck;
use App\Models\SponsorEvent;
use App\Models\SponsorshipRecord;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class RecordsAudit
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $meta = $this->meta($model);
        $this->audit->record([
            'action' => $meta['created'],
            'module' => $meta['module'],
            'employee_id' => $this->employeeId($model),
            'description' => $meta['label'].' created.',
            'new_value' => $this->summary($model),
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = collect($model->getChanges())->except(['updated_at', 'password', 'remember_token', 'email_verified_at']);
        if ($changes->isEmpty()) {
            return;
        }
        $meta = $this->meta($model);
        $action = $meta['updated'];
        if ($model instanceof LeaveRequest && $changes->has('status')) {
            $action = match ($model->status) {
                'Approved' => 'Leave approved',
                'Rejected' => 'Leave rejected',
                'Cancelled' => 'Leave cancelled',
                default => 'Leave updated',
            };
        }
        $original = collect($model->getOriginal())->only($changes->keys());
        $this->audit->record([
            'action' => $action,
            'module' => $meta['module'],
            'employee_id' => $this->employeeId($model),
            'description' => $meta['label'].' updated.',
            'previous_value' => $this->pairs($original),
            'new_value' => $this->pairs($changes),
        ]);
    }

    public function deleting(Model $model): void
    {
        $meta = $this->meta($model);
        $this->audit->record([
            'action' => $meta['removed'],
            'module' => $meta['module'],
            'employee_id' => $model instanceof User ? null : $this->employeeId($model),
            'description' => $meta['label'].' removed.',
            'previous_value' => $this->summary($model),
        ]);
    }

    /**
     * @return array{module: string, label: string, created: string, updated: string, removed: string}
     */
    private function meta(Model $model): array
    {
        return match (true) {
            $model instanceof User => ['module' => 'Employees', 'label' => 'Employee record', 'created' => 'Employee created', 'updated' => 'Employee updated', 'removed' => 'Employee removed'],
            $model instanceof Department => ['module' => 'Departments', 'label' => 'Department', 'created' => 'Department created', 'updated' => 'Department updated', 'removed' => 'Department removed'],
            $model instanceof AttendanceRecord => ['module' => 'Attendance', 'label' => 'Attendance record', 'created' => 'Attendance recorded', 'updated' => 'Attendance updated', 'removed' => 'Attendance removed'],
            $model instanceof AbsenceRecord => ['module' => 'Absence', 'label' => 'Absence record', 'created' => 'Absence recorded', 'updated' => 'Absence updated', 'removed' => 'Absence removed'],
            $model instanceof LeaveRequest => ['module' => 'Leave', 'label' => 'Leave request', 'created' => 'Leave requested', 'updated' => 'Leave updated', 'removed' => 'Leave removed'],
            $model instanceof Document => ['module' => 'Documents', 'label' => 'Document', 'created' => 'Document uploaded', 'updated' => 'Document updated', 'removed' => 'Document removed'],
            $model instanceof Vacancy => ['module' => 'Recruitment', 'label' => 'Vacancy', 'created' => 'Vacancy created', 'updated' => 'Vacancy updated', 'removed' => 'Vacancy removed'],
            $model instanceof Candidate => ['module' => 'Recruitment', 'label' => 'Candidate', 'created' => 'Candidate added', 'updated' => 'Candidate updated', 'removed' => 'Candidate removed'],
            $model instanceof RightToWorkCheck => ['module' => filled($model->immigration_category) || $model->permission_expiry ? 'Immigration' : 'Right to Work', 'label' => 'Right-to-work check', 'created' => 'Right-to-work check recorded', 'updated' => 'Right-to-work check updated', 'removed' => 'Right-to-work check removed'],
            $model instanceof SponsorshipRecord => ['module' => 'Sponsorship', 'label' => 'Sponsorship record', 'created' => 'Sponsored worker recorded', 'updated' => 'Sponsorship record updated', 'removed' => 'Sponsorship record removed'],
            $model instanceof SponsorEvent => ['module' => 'Sponsorship', 'label' => 'Sponsor event', 'created' => 'Sponsor event recorded', 'updated' => 'Sponsor event updated', 'removed' => 'Sponsor event removed'],
            $model instanceof CompanyChange => ['module' => 'Sponsorship', 'label' => 'Company change', 'created' => 'Company change recorded', 'updated' => 'Company change updated', 'removed' => 'Company change removed'],
            $model instanceof ComplianceReview => ['module' => 'Compliance', 'label' => 'Internal review', 'created' => 'Internal compliance review created', 'updated' => 'Internal review updated', 'removed' => 'Internal review removed'],
            $model instanceof EmployeeCompensation => ['module' => 'Payroll', 'label' => 'Salary record', 'created' => 'Salary change recorded', 'updated' => 'Salary record updated', 'removed' => 'Salary record removed'],
            $model instanceof PayrollRecord => ['module' => 'Payroll', 'label' => 'Payroll record', 'created' => 'Payroll evidence recorded', 'updated' => 'Payroll record updated', 'removed' => 'Payroll record removed'],
            $model instanceof HrTask => ['module' => 'Tasks', 'label' => 'HR task', 'created' => 'HR task created', 'updated' => 'HR task updated', 'removed' => 'HR task removed'],
            default => ['module' => class_basename($model), 'label' => class_basename($model), 'created' => class_basename($model).' created', 'updated' => class_basename($model).' updated', 'removed' => class_basename($model).' removed'],
        };
    }

    private function employeeId(Model $model): ?int
    {
        if ($model instanceof User) {
            return $model->id;
        }

        return isset($model->employee_id) ? (int) $model->employee_id : null;
    }

    private function summary(Model $model): ?string
    {
        foreach (['name', 'title', 'review_number', 'job_title', 'event_type', 'absence_type', 'leave_type', 'payroll_reference'] as $field) {
            if (filled($model->getAttribute($field))) {
                return (string) $model->getAttribute($field);
            }
        }

        return null;
    }

    private function pairs($values): ?string
    {
        $parts = collect($values)->map(function ($value, $key) {
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }

            return $key.': '.(filled($value) || $value === '0' || $value === 0 ? $value : '—');
        })->all();

        return $parts === [] ? null : implode('; ', $parts);
    }
}
