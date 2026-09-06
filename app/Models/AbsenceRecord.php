<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceRecord extends Model
{
    public const TYPES = ['Sick', 'Authorised Absence', 'Unauthorised Absence', 'Other'];

    protected $fillable = [
        'employee_id',
        'date',
        'absence_type',
        'reason',
        'reported_date',
        'how_reported',
        'reported_to',
        'expected_return',
        'manager_notes',
        'authorised',
        'follow_up_required',
        'attendance_record_id',
        'attendance_created',
        'actual_return',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reported_date' => 'date',
            'expected_return' => 'date',
            'authorised' => 'boolean',
            'follow_up_required' => 'boolean',
            'attendance_created' => 'boolean',
            'actual_return' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
