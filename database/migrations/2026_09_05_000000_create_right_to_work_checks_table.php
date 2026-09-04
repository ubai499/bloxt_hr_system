<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('right_to_work_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('check_date');
            $table->string('check_method');
            $table->string('performed_by')->nullable();
            $table->string('immigration_category')->nullable();
            $table->date('permission_start')->nullable();
            $table->date('permission_expiry')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('next_check_date')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->string('status')->default('Valid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('right_to_work_checks');
    }
};
