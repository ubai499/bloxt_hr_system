<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('department');
        });

        DB::table('users')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->orderBy('id')
            ->each(function (object $user): void {
                $departmentId = DB::table('departments')->where('name', $user->department)->value('id');

                if (! $departmentId) {
                    $departmentId = DB::table('departments')->insertGetId([
                        'name' => $user->department,
                        'status' => 'Active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('users')->where('id', $user->id)->update(['department_id' => $departmentId]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
