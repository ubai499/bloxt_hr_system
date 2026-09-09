<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorEvent extends Model
{
    public const TYPES = [
        'Change of work location', 'Salary change', 'Change in contracted hours', 'Role/job change',
        'Extended/unexplained absence', 'Employment termination', 'Worker does not start employment',
        'Change in immigration status', 'Organisation change', 'Other',
    ];

    public const STATUSES = ['Not Reportable', 'Requires Review', 'Report Required', 'Reported'];

    protected $fillable = [
        'employee_id', 'event_type', 'date_occurred', 'date_aware', 'details', 'requires_assessment',
        'reporting_deadline', 'assigned_to', 'reported_through_sms', 'date_reported', 'reported_by',
        'evidence_ref', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date_occurred' => 'date',
            'date_aware' => 'date',
            'reporting_deadline' => 'date',
            'date_reported' => 'date',
            'requires_assessment' => 'boolean',
            'reported_through_sms' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
