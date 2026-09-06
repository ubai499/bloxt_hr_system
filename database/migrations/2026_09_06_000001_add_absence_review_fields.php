<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_records', function (Blueprint $table) {
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->boolean('attendance_created')->default(false);
            $table->date('actual_return')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('absence_records', function (Blueprint $table) {
            $table->dropForeign(['attendance_record_id']);
            $table->dropIndex(['employee_id', 'date']);
            $table->dropColumn(['attendance_record_id', 'attendance_created', 'actual_return', 'reviewed_by', 'reviewed_at']);
        });
    }
};
