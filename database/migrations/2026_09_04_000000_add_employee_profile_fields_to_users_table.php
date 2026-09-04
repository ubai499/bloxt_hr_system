<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 50)->nullable()->unique()->after('id');
            $table->string('job_title')->nullable()->after('email');
            $table->string('department')->nullable()->after('job_title');
            $table->string('employment_type')->nullable()->after('department');
            $table->string('work_location')->nullable()->after('employment_type');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()->after('work_location');
            $table->date('start_date')->nullable()->after('manager_id');
            $table->string('phone')->nullable()->after('start_date');
            $table->string('status')->default('Active')->after('phone');
            $table->text('address')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropUnique(['employee_number']);
            $table->dropColumn([
                'employee_number',
                'job_title',
                'department',
                'employment_type',
                'work_location',
                'manager_id',
                'start_date',
                'phone',
                'status',
                'address',
            ]);
        });
    }
};
