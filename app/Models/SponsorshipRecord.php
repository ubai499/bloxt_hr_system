<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipRecord extends Model
{
    public const STATUSES = ['Current', 'Ended', 'Suspended'];

    public const ROUTES = ['Skilled Worker', 'Senior or Specialist Worker', 'Graduate', 'Other'];

    protected $fillable = [
        'employee_id', 'worker_route', 'sponsor_licence_ref', 'cos_reference', 'cos_assigned_date',
        'cos_start_date', 'cos_end_date', 'permission_start', 'permission_expiry', 'soc_code',
        'soc_title', 'internal_job_title', 'annual_salary', 'weekly_hours', 'work_pattern',
        'work_location', 'line_manager_id', 'sponsorship_status', 'hr_responsible_person',
        'next_review_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cos_assigned_date' => 'date',
            'cos_start_date' => 'date',
            'cos_end_date' => 'date',
            'permission_start' => 'date',
            'permission_expiry' => 'date',
            'next_review_date' => 'date',
            'annual_salary' => 'decimal:2',
            'weekly_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function lineManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    public function isCurrent(): bool
    {
        return $this->sponsorship_status === 'Current';
    }
}
