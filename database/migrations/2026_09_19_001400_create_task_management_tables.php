<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->dateTime('due_at', 6)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('pending');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'priority']);
            $table->index(['due_at', 'status']);
            $table->index(['archived_at', 'status']);
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('event_tasks')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('staff_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at', 6);
            $table->timestamps(6);
            $table->unique(['task_id', 'user_id'], 'task_assignment_user_unique');
            $table->unique(['task_id', 'staff_profile_id'], 'task_assignment_staff_unique');
            $table->index(['user_id', 'assigned_at']);
            $table->index(['staff_profile_id', 'assigned_at']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('event_tasks')->restrictOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps(6);
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('event_tasks')->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('attached_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps(6);
            $table->unique(['task_id', 'document_id']);
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('task_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('event_tasks')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('actor_type', 30)->default('user');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at', 6);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->index(['task_id', 'changed_at']);
            $table->index(['actor_user_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_histories');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('event_tasks');
    }
};
