<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('right_to_work_checks', function (Blueprint $table) {
            $table->string('restrictions')->nullable()->after('immigration_category');
        });
    }

    public function down(): void
    {
        Schema::table('right_to_work_checks', function (Blueprint $table) {
            $table->dropColumn('restrictions');
        });
    }
};
