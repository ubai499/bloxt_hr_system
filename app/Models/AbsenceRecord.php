<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceRecord extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reported_date' => 'date',
            'expected_return' => 'date',
            'authorised' => 'boolean',
            'follow_up_required' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
