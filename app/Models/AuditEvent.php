<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditEvent extends Model
{
    public const MODULES = [
        'Employees', 'Departments', 'Attendance', 'Absence', 'Leave', 'Documents', 'Recruitment',
        'Right to Work', 'Immigration', 'Sponsorship', 'Compliance', 'Payroll', 'Tasks', 'Notifications',
    ];

    protected $fillable = [
        'occurred_at', 'user_id', 'user_name', 'action', 'module', 'employee_id',
        'description', 'previous_value', 'new_value', 'device',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit events cannot be changed.'));
        static::deleting(fn () => throw new RuntimeException('Audit events cannot be removed.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
