<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    public const FREQUENCIES = ['Annual', 'Hourly', 'Monthly'];

    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_id',
        'annual_salary',
        'salary_frequency',
        'hourly_rate',
        'contracted_hours',
        'previous_salary',
        'effective_date',
        'reason',
        'authorised_by',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'annual_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'contracted_hours' => 'decimal:2',
            'previous_salary' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
