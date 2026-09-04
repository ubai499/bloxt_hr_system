<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'expected_start',
        'clock_in',
        'clock_out',
        'hours',
        'work_location',
        'status',
        'notes',
        'manager_reviewed',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'manager_reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
