<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCompensation extends Model
{
    // Keep the model aligned with the plural Laravel migration table name.
    protected $table = 'employee_compensations';

    protected $fillable = ['employee_id', 'annual_salary', 'salary_frequency', 'hourly_rate', 'effective_date', 'reason', 'authorised_by'];
}
