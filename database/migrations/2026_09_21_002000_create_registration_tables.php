<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('name', 191);
            $table->string('public_slug', 64)->unique();
            $table->text('privacy_text')->nullable();
            $table->string('duplicate_policy', 32)->default('block_email');
            $table->boolean('approval_required')->default(true);
            $table->string('confirmation_channel', 16)->default('none');
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at', 6)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'is_active', 'published_at'], 'registration_forms_event_state_idx');
        });

        Schema::create('registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_form_id')->constrained()->restrictOnDelete();
            $table->string('key', 64);
            $table->string('type', 24);
            $table->string('label', 191);
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation_constraints')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['registration_form_id', 'key'], 'registration_fields_form_key_unique');
            $table->index(['registration_form_id', 'is_active', 'display_order'], 'registration_fields_form_order_idx');
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_form_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->restrictOnDelete();
            $table->string('reference_number', 40)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->string('source', 16);
            $table->string('status', 24)->default('pending');
            $table->string('registrant_name', 191);
            $table->string('registrant_email', 320)->nullable();
            $table->string('normalized_email', 320)->nullable();
            $table->string('registrant_phone', 50)->nullable();
            $table->timestamp('submitted_at', 6);
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('confirmation_status', 24)->default('not_requested');
            $table->timestamp('confirmation_sent_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'submitted_at'], 'registrations_event_status_submitted_idx');
            $table->index(['registration_form_id', 'normalized_email'], 'registrations_form_email_idx');
            $table->index(['event_id', 'reference_number'], 'registrations_event_reference_idx');
        });

        Schema::create('registration_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_field_id')->constrained()->restrictOnDelete();
            $table->string('field_key', 64);
            $table->string('field_label', 191);
            $table->string('field_type', 24);
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps(6);
            $table->unique(['registration_id', 'registration_field_id'], 'registration_responses_registration_field_unique');
            $table->index(['event_id', 'field_key'], 'registration_responses_event_key_idx');
        });

        Schema::create('registration_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('actor_type', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at', 6);
            $table->index(['registration_id', 'changed_at'], 'registration_status_history_registration_time_idx');
        });

        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->foreignId('registration_id')->nullable()->after('booking_id')->constrained('registrations')->restrictOnDelete();
            $table->index(['registration_id', 'created_at'], 'outbound_messages_registration_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->dropIndex('outbound_messages_registration_time_idx');
            $table->dropColumn('registration_id');
        });
        Schema::dropIfExists('registration_status_histories');
        Schema::dropIfExists('registration_responses');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('registration_fields');
        Schema::dropIfExists('registration_forms');
    }
};
