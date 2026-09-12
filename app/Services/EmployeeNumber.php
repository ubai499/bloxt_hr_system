<?php

namespace App\Services;

use App\Models\User;

class EmployeeNumber
{
    public const PREFIX = 'BXT-';

    public function next(): string
    {
        $highest = 0;

        foreach (User::query()->whereNotNull('employee_number')->pluck('employee_number') as $number) {
            if (preg_match('/^'.preg_quote(self::PREFIX, '/').'(\d+)$/i', (string) $number, $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return sprintf('%s%03d', self::PREFIX, $highest + 1);
    }
}
