<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCompensation extends Model
{
    protected $fillable = ['employee_id', 'annual_salary', 'salary_frequency', 'hourly_rate', 'effective_date', 'reason', 'authorised_by'];
}
