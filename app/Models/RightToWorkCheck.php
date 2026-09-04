<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RightToWorkCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'check_date',
        'check_method',
        'performed_by',
        'immigration_category',
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

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function directoryStatus(): string
    {
        if (! $this->permission_expiry) {
            return $this->status ?: 'Valid';
        }

        $daysUntilExpiry = now()->startOfDay()->diffInDays($this->permission_expiry->startOfDay(), false);

        return match (true) {
            $daysUntilExpiry < 0 => 'Expired',
            $daysUntilExpiry <= 30 => 'Expiring Soon',
            $daysUntilExpiry <= 90 => 'Review Due',
            default => 'Valid',
        };
    }

    public function isSponsored(): bool
    {
        return $this->check_method === 'Online Home Office check' && $this->permission_expiry !== null;
    }
}
