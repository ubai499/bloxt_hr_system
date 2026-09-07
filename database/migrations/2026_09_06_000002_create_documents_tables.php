<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name')->nullable();
            $table->string('title');
            $table->string('category')->index();
            $table->string('access_classification');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->date('review_date')->nullable();
            $table->string('status')->default('Valid');
            $table->string('archive_status')->default('Active');
            $table->unsignedInteger('version')->default(1);
            $table->string('retention_category');
            $table->date('retention_until')->nullable();
            $table->text('retention_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_name');
            $table->date('upload_date');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });

        Schema::create('document_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action');
            $table->json('metadata');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('documents');
    }
};
