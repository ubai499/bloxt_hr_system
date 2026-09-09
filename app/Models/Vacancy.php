<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vacancy extends Model
{
    public const EMPLOYMENT_TYPES = ['Permanent', 'Fixed-term', 'Part-time', 'Temporary', 'Contractor', 'Intern'];

    public const STATUSES = ['Open', 'On Hold', 'Closed', 'Filled'];

    protected $fillable = [
        'job_title',
        'department_id',
        'hiring_manager',
        'employment_type',
        'opening_date',
        'closing_date',
        'salary_range_min',
        'salary_range_max',
        'location',
        'recruitment_channel',
        'reason_for_vacancy',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'closing_date' => 'date',
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->latest('application_date')->orderBy('id');
    }

    public function acceptsCandidates(): bool
    {
        return in_array($this->status, ['Open', 'On Hold'], true);
    }
}
