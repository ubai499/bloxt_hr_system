<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('annual_salary', 12, 2);
            $table->string('salary_frequency')->default('Annual');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->date('effective_date');
            $table->string('reason')->nullable();
            $table->string('authorised_by')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('employee_compensations'); }
};
