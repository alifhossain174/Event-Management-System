<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 160);
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 80);
            $table->string('to_status', 80);
            $table->string('actor_type', 20)->default('user');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at', 6);
            $table->index(['subject_type', 'subject_id', 'changed_at'], 'status_history_subject_time_idx');
            $table->index(['actor_user_id', 'changed_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'expiry_date']);
            $table->index(['document_category_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk', 50);
            $table->string('path', 512)->unique();
            $table->string('original_name');
            $table->string('extension', 20);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6);
            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'created_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('uploaded_by_user_id')
                ->constrained('document_versions')->restrictOnDelete();
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type', 160);
            $table->unsignedBigInteger('linkable_id');
            $table->string('relationship', 60)->default('attachment');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6);
            $table->unique(['document_id', 'linkable_type', 'linkable_id'], 'document_link_context_unique');
            $table->index(['linkable_type', 'linkable_id'], 'document_link_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
        Schema::table('documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_version_id'));
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('status_histories');
    }
};
