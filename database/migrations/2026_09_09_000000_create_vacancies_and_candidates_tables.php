<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('job_title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('hiring_manager')->nullable();
            $table->string('employment_type')->nullable();
            $table->date('opening_date')->nullable()->index();
            $table->date('closing_date')->nullable()->index();
            $table->decimal('salary_range_min', 10, 2)->nullable();
            $table->decimal('salary_range_max', 10, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('recruitment_channel')->nullable();
            $table->string('reason_for_vacancy')->nullable();
            $table->string('status')->default('Open')->index();
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->string('name');
            $table->date('application_date')->nullable()->index();
            $table->string('source')->nullable();
            $table->text('interview_records')->nullable();
            $table->string('outcome')->default('In Progress')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('vacancies');
    }
};
