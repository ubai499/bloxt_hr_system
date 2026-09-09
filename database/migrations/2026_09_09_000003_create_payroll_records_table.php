<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('payroll_period');
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('overtime', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->date('payment_date');
            $table->string('payroll_reference');
            $table->boolean('evidence_uploaded')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_period']);
            $table->unique('payroll_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
