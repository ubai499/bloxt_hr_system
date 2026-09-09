<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyChange extends Model
{
    public const TYPES = [
        'Registered office change', 'Trading name change', 'Key personnel change',
        'Ownership change', 'Work location change', 'Organisation change', 'Other',
    ];

    protected $fillable = [
        'change_type', 'date', 'description', 'reported_internally_by', 'reviewed_by',
        'potential_sponsor_impact', 'report_required', 'reported', 'reported_date',
        'evidence_ref', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reported_date' => 'date',
            'report_required' => 'boolean',
            'reported' => 'boolean',
        ];
    }
}
