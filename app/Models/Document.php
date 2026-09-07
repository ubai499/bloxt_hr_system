<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    public const CATEGORIES = ['Identity', 'Right to Work', 'Employment Contract', 'Job Description', 'Recruitment', 'Qualifications', 'Professional Accreditation', 'Immigration', 'Payroll', 'Policies', 'Training', 'Performance', 'Sponsorship', 'Other'];

    public const STATUSES = ['Valid', 'Review Due', 'Expiring Soon', 'Expired', 'Archived'];

    public const CLASSIFICATIONS = ['Normal', 'Confidential', 'Highly Confidential'];

    public const RETENTION_CATEGORIES = ['Standard (6 years)', 'Immigration record (statutory)', 'Payroll (6 years)', 'Permanent'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return array_fill_keys(['issue_date', 'expiry_date', 'review_date', 'retention_until', 'upload_date'], 'date');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function displayStatus(): string
    {
        if ($this->archive_status === 'Archived' || $this->status === 'Archived') {
            return 'Archived';
        }
        if ($this->expiry_date?->lt(today())) {
            return 'Expired';
        }
        if ($this->expiry_date && $this->expiry_date->lte(today()->addDays(14))) {
            return 'Expiring Soon';
        }
        if (($this->review_date && $this->review_date->lte(today()))
            || ($this->expiry_date && $this->expiry_date->lte(today()->addDays(90)))
            || $this->status === 'Review Due') {
            return 'Review Due';
        }

        return 'Valid';
    }
}
