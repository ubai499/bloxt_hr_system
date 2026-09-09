<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuidanceReference extends Model
{
    protected $fillable = ['title', 'source', 'url', 'last_reviewed', 'reviewed_by', 'notes'];

    protected function casts(): array
    {
        return [
            'last_reviewed' => 'date',
        ];
    }
}
