<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_number',
        'name',
        'email',
        'password',
        'job_title',
        'department_id',
        'employment_type',
        'work_location',
        'manager_id',
        'start_date',
        'phone',
        'status',
        'address',
        'personal_email',
        'date_of_birth',
        'nationality',
        'postcode',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'end_date',
        'probation_end_date',
        'work_arrangement',
        'weekly_hours',
        'normal_working_hours',
        'leave_allowance',
        'contact_verified_date',
        'contact_verified_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'leave_allowance' => 'decimal:2',
            'weekly_hours' => 'decimal:2',
            'contact_verified_date' => 'date',
        ];
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'employee_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function absenceRecords()
    {
        return $this->hasMany(AbsenceRecord::class, 'employee_id');
    }

    public function departmentRecord()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function rightToWorkChecks()
    {
        return $this->hasMany(RightToWorkCheck::class, 'employee_id');
    }

    public function latestRightToWorkCheck()
    {
        return $this->hasOne(RightToWorkCheck::class, 'employee_id')->latestOfMany('check_date');
    }

    public function compensations()
    {
        return $this->hasMany(EmployeeCompensation::class, 'employee_id');
    }

    public function latestCompensation()
    {
        return $this->hasOne(EmployeeCompensation::class, 'employee_id')->latestOfMany('effective_date');
    }

    public function payrollRecords()
    {
        return $this->hasMany(PayrollRecord::class, 'employee_id');
    }

    public function sponsorshipRecord()
    {
        return $this->hasOne(SponsorshipRecord::class, 'employee_id');
    }

    public function currentSponsorship()
    {
        return $this->hasOne(SponsorshipRecord::class, 'employee_id')->where('sponsorship_status', 'Current');
    }

    public function sponsorEvents()
    {
        return $this->hasMany(SponsorEvent::class, 'employee_id');
    }

    public function auditEvents()
    {
        return $this->hasMany(AuditEvent::class, 'employee_id')->latest('occurred_at')->latest('id');
    }

    public function hrTasks()
    {
        return $this->hasMany(HrTask::class, 'employee_id');
    }
}
