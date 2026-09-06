<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('leave_allowance', 6, 2)->default(25);
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable();
            $table->index(['employee_id', 'status', 'from_date', 'to_date'], 'leave_employee_dates_index');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('leave_employee_dates_index');
            $table->dropColumn('rejection_reason');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('leave_allowance'));
    }
};
