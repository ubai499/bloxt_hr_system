<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceReview extends Model
{
    public const RESULTS = ['No Action', 'Follow-up Required', 'Action Plan Open', 'Completed'];

    protected $fillable = [
        'review_number', 'review_date', 'reviewer', 'area', 'employees_sampled', 'records_reviewed',
        'issues_found', 'actions_required', 'responsible_person', 'due_date', 'completion_date',
        'evidence_ref', 'notes', 'result',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'due_date' => 'date',
            'completion_date' => 'date',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->completion_date === null && $this->due_date->lt(today());
    }
}
