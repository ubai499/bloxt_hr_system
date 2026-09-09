<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RightToWorkCheck extends Model
{
    use HasFactory;

    public const METHODS = ['Online Home Office check', 'Manual document check', 'Other permitted method'];

    public const STATUSES = ['Valid', 'Review Due', 'Expiring Soon', 'Expired', 'Evidence Missing', 'Follow-up Required', 'Not Applicable'];

    protected $fillable = [
        'employee_id',
        'check_date',
        'check_method',
        'performed_by',
        'immigration_category',
        'restrictions',
        'permission_start',
        'permission_expiry',
        'follow_up_required',
        'next_check_date',
        'evidence_reference',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_date' => 'date',
            'permission_start' => 'date',
            'permission_expiry' => 'date',
            'next_check_date' => 'date',
            'follow_up_required' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function displayStatus(): string
    {
        if ($this->permission_expiry) {
            $daysUntilExpiry = now()->startOfDay()->diffInDays($this->permission_expiry->startOfDay(), false);

            return match (true) {
                $daysUntilExpiry < 0 => 'Expired',
                $daysUntilExpiry <= 30 => 'Expiring Soon',
                $daysUntilExpiry <= 90 => 'Review Due',
                $this->follow_up_required || $this->status === 'Follow-up Required' => 'Follow-up Required',
                default => 'Valid',
            };
        }

        if ($this->follow_up_required) {
            return 'Follow-up Required';
        }

        return in_array($this->status, self::STATUSES, true) ? $this->status : 'Valid';
    }

    public function directoryStatus(): string
    {
        return $this->displayStatus();
    }

    public function isSponsored(): bool
    {
        return $this->check_method === 'Online Home Office check' && $this->permission_expiry !== null;
    }
}
