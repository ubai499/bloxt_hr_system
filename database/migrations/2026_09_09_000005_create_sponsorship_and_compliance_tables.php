<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_licences', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('Valid');
            $table->string('reference')->nullable();
            $table->string('rating')->nullable();
            $table->date('start_date')->nullable();
            $table->date('renewal_review_date')->nullable();
            $table->json('worker_routes')->nullable();
            $table->string('authorising_officer')->nullable();
            $table->string('key_contact')->nullable();
            $table->string('level1_user')->nullable();
            $table->json('level2_users')->nullable();
            $table->date('org_details_last_reviewed')->nullable();
            $table->date('next_internal_review_date')->nullable();
            $table->string('sms_url')->default('https://www.gov.uk/sponsor-management-system');
            $table->timestamps();
        });

        DB::table('sponsor_licences')->insert([
            'status' => 'Valid',
            'reference' => 'SPN-8842-2024',
            'rating' => 'A-Rating',
            'start_date' => '2024-03-01',
            'renewal_review_date' => '2027-03-01',
            'worker_routes' => json_encode(['Skilled Worker']),
            'authorising_officer' => 'Louise Farrington',
            'key_contact' => 'Sarah Whitfield',
            'level1_user' => 'Sarah Whitfield',
            'level2_users' => json_encode(['Meera Iyer']),
            'org_details_last_reviewed' => '2026-04-10',
            'next_internal_review_date' => '2026-10-10',
            'sms_url' => 'https://www.gov.uk/sponsor-management-system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('sponsorship_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('worker_route');
            $table->string('sponsor_licence_ref')->nullable();
            $table->string('cos_reference')->nullable();
            $table->date('cos_assigned_date')->nullable();
            $table->date('cos_start_date')->nullable();
            $table->date('cos_end_date')->nullable();
            $table->date('permission_start')->nullable();
            $table->date('permission_expiry')->nullable();
            $table->string('soc_code')->nullable();
            $table->string('soc_title')->nullable();
            $table->string('internal_job_title')->nullable();
            $table->decimal('annual_salary', 12, 2)->nullable();
            $table->decimal('weekly_hours', 5, 2)->nullable();
            $table->string('work_pattern')->nullable();
            $table->string('work_location')->nullable();
            $table->foreignId('line_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sponsorship_status')->default('Current');
            $table->string('hr_responsible_person')->nullable();
            $table->date('next_review_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sponsor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type');
            $table->date('date_occurred')->nullable();
            $table->date('date_aware')->nullable();
            $table->text('details');
            $table->boolean('requires_assessment')->default(true);
            $table->date('reporting_deadline')->nullable();
            $table->string('assigned_to')->nullable();
            $table->boolean('reported_through_sms')->default(false);
            $table->date('date_reported')->nullable();
            $table->string('reported_by')->nullable();
            $table->string('evidence_ref')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('Requires Review');
            $table->timestamps();
        });

        Schema::create('company_changes', function (Blueprint $table) {
            $table->id();
            $table->string('change_type');
            $table->date('date');
            $table->text('description');
            $table->string('reported_internally_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('potential_sponsor_impact')->nullable();
            $table->boolean('report_required')->default(false);
            $table->boolean('reported')->default(false);
            $table->date('reported_date')->nullable();
            $table->string('evidence_ref')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('guidance_references', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source')->nullable();
            $table->string('url');
            $table->date('last_reviewed')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('guidance_references')->insert([
            'title' => 'GOV.UK guidance and services home',
            'source' => 'GOV.UK',
            'url' => 'https://www.gov.uk',
            'last_reviewed' => '2026-06-01',
            'reviewed_by' => 'Meera Iyer',
            'notes' => 'Starting point for locating current sponsor duties, right-to-work and Skilled Worker guidance.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('compliance_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_number')->unique();
            $table->date('review_date');
            $table->string('reviewer')->nullable();
            $table->string('area')->nullable();
            $table->unsignedInteger('employees_sampled')->default(0);
            $table->string('records_reviewed')->nullable();
            $table->text('issues_found')->nullable();
            $table->text('actions_required')->nullable();
            $table->string('responsible_person')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('evidence_ref')->nullable();
            $table->text('notes')->nullable();
            $table->string('result')->default('Follow-up Required');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reviews');
        Schema::dropIfExists('guidance_references');
        Schema::dropIfExists('company_changes');
        Schema::dropIfExists('sponsor_events');
        Schema::dropIfExists('sponsorship_records');
        Schema::dropIfExists('sponsor_licences');
    }
};
