<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name');
            $table->string('action');
            $table->string('module');
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('previous_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('device')->default('Web session');
            $table->timestamps();
            $table->index(['module', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('hr_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('General');
            $table->string('assigned_to')->nullable();
            $table->string('priority')->default('Medium');
            $table->date('due_date')->nullable();
            $table->string('status')->default('Open');
            $table->timestamps();
        });

        Schema::create('hr_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('Unread');
            $table->string('href')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_notifications');
        Schema::dropIfExists('hr_tasks');
        Schema::dropIfExists('audit_events');
    }
};
