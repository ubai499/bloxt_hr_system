<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\HrNotification;
use App\Models\User;

class AuditLogger
{
    public function record(array $entry, bool $notify = false): AuditEvent
    {
        $user = auth()->user();
        $event = AuditEvent::create([
            'occurred_at' => $entry['occurred_at'] ?? now(),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?: 'System',
            'action' => $entry['action'],
            'module' => $entry['module'],
            'employee_id' => $entry['employee_id'] ?? null,
            'description' => $entry['description'] ?? null,
            'previous_value' => $this->clip($entry['previous_value'] ?? null),
            'new_value' => $this->clip($entry['new_value'] ?? null),
            'device' => $entry['device'] ?? 'Web session',
        ]);

        if ($notify) {
            HrNotification::create([
                'user_id' => null,
                'title' => $entry['action'],
                'body' => $entry['description'] ?? $entry['action'],
                'status' => 'Unread',
                'href' => $entry['href'] ?? route('admin.audit-log.index', ['highlight' => $event->id]),
            ]);
        }

        return $event;
    }

    public function forEmployee(User $employee, int $limit = 50)
    {
        return AuditEvent::query()->where('employee_id', $employee->id)
            ->latest('occurred_at')->latest('id')->limit($limit)->get();
    }

    private function clip(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return mb_strlen($value) > 2000 ? mb_substr($value, 0, 1997).'...' : $value;
    }
}
