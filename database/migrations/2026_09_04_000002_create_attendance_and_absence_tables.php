<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('expected_start')->nullable();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->decimal('hours', 5, 2)->default(0);
            $table->string('work_location')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->boolean('manager_reviewed')->default(false);
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });

        Schema::create('absence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('absence_type');
            $table->string('reason')->nullable();
            $table->date('reported_date')->nullable();
            $table->string('how_reported')->nullable();
            $table->string('reported_to')->nullable();
            $table->date('expected_return')->nullable();
            $table->text('manager_notes')->nullable();
            $table->boolean('authorised')->default(false);
            $table->boolean('follow_up_required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_records');
        Schema::dropIfExists('attendance_records');
    }
};
