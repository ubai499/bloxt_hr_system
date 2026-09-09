<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTask extends Model
{
    public const STATUSES = ['Open', 'In Progress', 'Awaiting Information', 'Completed', 'Cancelled'];

    public const PRIORITIES = ['High', 'Medium', 'Low'];

    protected $fillable = [
        'title', 'description', 'employee_id', 'category', 'assigned_to', 'priority', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
