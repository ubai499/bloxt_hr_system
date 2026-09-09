<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('contact_verified_date')->nullable()->after('leave_allowance');
            $table->string('contact_verified_by')->nullable()->after('contact_verified_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contact_verified_date', 'contact_verified_by']);
        });
    }
};
