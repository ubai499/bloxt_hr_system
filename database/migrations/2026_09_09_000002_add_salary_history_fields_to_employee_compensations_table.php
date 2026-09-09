<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->decimal('contracted_hours', 5, 2)->nullable()->after('hourly_rate');
            $table->decimal('previous_salary', 12, 2)->nullable()->after('contracted_hours');
            $table->string('recorded_by')->nullable()->after('authorised_by');
        });

        $hours = DB::table('users')->pluck('weekly_hours', 'id');
        foreach (DB::table('employee_compensations')->orderBy('id')->get() as $row) {
            DB::table('employee_compensations')->where('id', $row->id)->update([
                'contracted_hours' => $hours[$row->employee_id] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->dropColumn(['contracted_hours', 'previous_salary', 'recorded_by']);
        });
    }
};
