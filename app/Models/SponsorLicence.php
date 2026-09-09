<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorLicence extends Model
{
    public const STATUSES = ['Valid', 'Pending', 'Suspended', 'Revoked'];

    protected $fillable = [
        'status', 'reference', 'rating', 'start_date', 'renewal_review_date', 'worker_routes',
        'authorising_officer', 'key_contact', 'level1_user', 'level2_users',
        'org_details_last_reviewed', 'next_internal_review_date', 'sms_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'renewal_review_date' => 'date',
            'org_details_last_reviewed' => 'date',
            'next_internal_review_date' => 'date',
            'worker_routes' => 'array',
            'level2_users' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'status' => 'Valid',
            'sms_url' => 'https://www.gov.uk/sponsor-management-system',
        ]);
    }
}
