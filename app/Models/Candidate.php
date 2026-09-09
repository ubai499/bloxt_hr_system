<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    public const OUTCOMES = ['In Progress', 'Offered', 'Hired', 'Rejected', 'Withdrawn'];

    protected $fillable = [
        'vacancy_id',
        'name',
        'application_date',
        'source',
        'interview_records',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }
}
