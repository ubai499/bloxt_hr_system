<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('personal_email')->nullable()->after('email');
            $table->date('date_of_birth')->nullable()->after('personal_email');
            $table->string('nationality')->nullable()->after('date_of_birth');
            $table->string('postcode', 20)->nullable()->after('nationality');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_relationship');
            $table->date('end_date')->nullable()->after('start_date');
            $table->date('probation_end_date')->nullable()->after('end_date');
            $table->string('work_arrangement')->nullable()->after('work_location');
            $table->decimal('weekly_hours', 5, 2)->nullable()->after('work_arrangement');
            $table->string('normal_working_hours')->nullable()->after('weekly_hours');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['personal_email', 'date_of_birth', 'nationality', 'postcode', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'end_date', 'probation_end_date', 'work_arrangement', 'weekly_hours', 'normal_working_hours']);
        });
    }
};
