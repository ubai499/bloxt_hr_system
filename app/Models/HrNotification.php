<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrNotification extends Model
{
    public const STATUSES = ['Unread', 'Read', 'Resolved'];

    protected $fillable = ['user_id', 'title', 'body', 'status', 'href'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id));
    }
}
