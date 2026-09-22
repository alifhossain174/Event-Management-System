<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();
            $table->foreignId('source_event_id')->nullable()->constrained('events')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->foreignId('event_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->string('status', 30)->default('draft');
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->string('timezone', 64);
            $table->string('primary_contact_name', 180)->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('primary_contact_phone', 60)->nullable();
            $table->unsignedInteger('expected_guest_count')->nullable();
            $table->string('theme', 180)->nullable();
            $table->string('dress_code', 180)->nullable();
            $table->text('description')->nullable();
            $table->decimal('core_budget_estimate', 19, 4)->nullable();
            $table->char('currency_code', 3);
            $table->json('template_snapshot')->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('archived_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['status', 'starts_at']);
            $table->index(['client_id', 'status']);
            $table->index(['branch_id', 'starts_at']);
            $table->index(['manager_user_id', 'status']);
            $table->index(['event_category_id', 'starts_at'], 'events_category_start_idx');
            $table->index(['archived_at', 'status']);
        });

        Schema::create('event_module_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('module_key', 64);
            $table->boolean('is_enabled')->default(false);
            $table->string('source', 30)->default('manual');
            $table->foreignId('enabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('enabled_at', 6)->nullable();
            $table->foreignId('disabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disabled_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('module_key')->references('key')->on('module_definitions')->restrictOnDelete();
            $table->unique(['event_id', 'module_key']);
            $table->index(['module_key', 'is_enabled']);
        });

        Schema::create('event_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('actor_type', 20)->default('user');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('changed_at', 6);
            $table->index(['event_id', 'changed_at']);
            $table->index(['actor_user_id', 'changed_at']);
        });

        Schema::create('event_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('duplicated_from_note_id')->nullable()->constrained('event_notes')->restrictOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('include_in_duplicate')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['event_id', 'is_pinned', 'created_at'], 'event_notes_event_pin_time_idx');
        });

        Schema::create('event_timeline_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('type', 50);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at', 6);
            $table->index(['event_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_timeline_items');
        Schema::dropIfExists('event_notes');
        Schema::dropIfExists('event_status_histories');
        Schema::dropIfExists('event_module_settings');
        Schema::dropIfExists('events');
    }
};
