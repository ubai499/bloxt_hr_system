<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    public const TYPES = ['Annual leave', 'Sick leave', 'Unpaid leave', 'Compassionate leave', 'Parental leave', 'Other leave'];

    public const STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

    protected $fillable = [
        'employee_id',
        'leave_type',
        'from_date',
        'to_date',
        'partial_day',
        'reason',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'requested_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'partial_day' => 'boolean',
            'approved_at' => 'datetime',
            'requested_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
