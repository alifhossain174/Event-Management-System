<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->string('route_name', 128)->nullable();
            $table->json('route_parameters')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamps(6);
            $table->index(['type', 'created_at'], 'notifications_type_created_idx');
            $table->index(['source_type', 'source_id'], 'notifications_source_idx');
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamp('read_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['notification_id', 'user_id'], 'notification_recipient_user_unique');
            $table->index(['user_id', 'read_at', 'created_at'], 'notification_recipients_inbox_idx');
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->nullable()->unique();
            $table->string('name', 191);
            $table->string('channel', 24)->default('email');
            $table->string('category', 24)->default('operational');
            $table->string('subject', 191)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['channel', 'category', 'is_active'], 'message_templates_channel_category_active_idx');
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 24);
            $table->string('category', 24)->default('operational');
            $table->string('status', 24)->default('pending');
            $table->string('subject', 191)->nullable();
            $table->text('body');
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at', 6)->nullable();
            $table->timestamp('sent_at', 6)->nullable();
            $table->timestamp('failed_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'created_at'], 'outbound_messages_event_status_idx');
            $table->index(['client_id', 'created_at'], 'outbound_messages_client_idx');
            $table->index(['booking_id', 'created_at'], 'outbound_messages_booking_idx');
        });

        Schema::create('message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_message_id')->constrained()->restrictOnDelete();
            $table->string('recipient_type', 24)->default('to');
            $table->string('address', 320);
            $table->string('display_name', 191)->nullable();
            $table->string('consent_basis', 32)->default('operational');
            $table->string('status', 24)->default('pending');
            $table->string('last_error_code', 100)->nullable();
            $table->timestamp('sent_at', 6)->nullable();
            $table->timestamp('failed_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['outbound_message_id', 'status'], 'message_recipients_message_status_idx');
        });

        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_message_id')->constrained()->restrictOnDelete();
            $table->foreignId('message_recipient_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('provider', 64);
            $table->string('status', 24);
            $table->string('provider_reference', 191)->nullable();
            $table->string('sanitized_error', 255)->nullable();
            $table->foreignId('attempted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attempted_at', 6);
            $table->index(['outbound_message_id', 'attempted_at'], 'delivery_logs_message_attempted_idx');
            $table->unique(['message_recipient_id', 'attempt_number'], 'delivery_logs_recipient_attempt_unique');
        });

        Schema::create('reminder_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_type', 64);
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('target_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 24)->default('in_app');
            $table->string('status', 24)->default('scheduled');
            $table->timestamp('due_at', 6);
            $table->string('idempotency_key', 191)->unique();
            $table->timestamp('processed_at', 6)->nullable();
            $table->timestamp('last_attempt_at', 6)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['status', 'due_at'], 'reminder_schedules_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_schedules');
        Schema::dropIfExists('delivery_logs');
        Schema::dropIfExists('message_recipients');
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }
};
