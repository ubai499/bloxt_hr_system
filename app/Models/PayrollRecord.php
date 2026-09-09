<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'payroll_period',
        'gross_salary',
        'basic_salary',
        'overtime',
        'bonus',
        'deductions',
        'net_amount',
        'payment_date',
        'payroll_reference',
        'evidence_uploaded',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'basic_salary' => 'decimal:2',
            'overtime' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'payment_date' => 'date',
            'evidence_uploaded' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
