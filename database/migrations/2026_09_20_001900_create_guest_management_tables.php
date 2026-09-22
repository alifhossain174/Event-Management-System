<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('name', 191);
            $table->string('type', 32)->default('family');
            $table->text('description')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'archived_at', 'name'], 'guest_groups_event_archive_name_idx');
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('external_reference', 100)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 191);
            $table->string('normalized_name', 191);
            $table->string('email', 320)->nullable();
            $table->string('normalized_email', 320)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('normalized_phone', 30)->nullable();
            $table->boolean('is_vip')->default(false);
            $table->string('invitation_status', 32)->default('not_invited');
            $table->string('rsvp_status', 32)->default('pending');
            $table->string('plus_one_policy', 32)->default('none');
            $table->unsignedSmallInteger('plus_one_limit')->default(0);
            $table->unsignedSmallInteger('invited_party_size')->default(1);
            $table->unsignedSmallInteger('confirmed_party_size')->default(0);
            $table->string('source', 32)->default('manual');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps(6);
            $table->unique(['event_id', 'external_reference'], 'guests_event_external_reference_unique');
            $table->index(['event_id', 'rsvp_status', 'archived_at'], 'guests_event_rsvp_archive_idx');
            $table->index(['event_id', 'invitation_status', 'archived_at'], 'guests_event_invitation_archive_idx');
            $table->index(['event_id', 'is_vip', 'archived_at'], 'guests_event_vip_archive_idx');
            $table->index(['event_id', 'normalized_name'], 'guests_event_name_idx');
            $table->index(['event_id', 'normalized_email'], 'guests_event_email_idx');
            $table->index(['event_id', 'normalized_phone'], 'guests_event_phone_idx');
        });

        Schema::create('guest_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->string('relationship_label', 80)->nullable();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at', 6);
            $table->unique('guest_id', 'guest_group_members_guest_unique');
            $table->unique(['guest_group_id', 'guest_id'], 'guest_group_members_group_guest_unique');
            $table->index(['event_id', 'guest_group_id'], 'guest_group_members_event_group_idx');
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('issued');
            $table->string('delivery_channel', 32)->default('manual');
            $table->char('check_in_token_hash', 64)->unique();
            $table->text('check_in_token_encrypted');
            $table->timestamp('issued_at', 6);
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at', 6)->nullable();
            $table->timestamp('expires_at', 6)->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'issued_at'], 'invitations_event_status_issued_idx');
            $table->index(['guest_id', 'status'], 'invitations_guest_status_idx');
        });

        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attending_count')->default(0);
            $table->unsignedSmallInteger('plus_one_count')->default(0);
            $table->string('response_source', 32)->default('manager');
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at', 6)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'responded_at'], 'rsvps_event_status_responded_idx');
        });

        Schema::create('seat_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('guest_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('table_label', 100);
            $table->string('seat_label', 100);
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at', 6);
            $table->timestamps(6);
            $table->unique(['event_id', 'table_label', 'seat_label'], 'seat_assignments_event_table_seat_unique');
            $table->index(['event_id', 'table_label'], 'seat_assignments_event_table_idx');
        });

        Schema::create('guest_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->string('visibility', 24)->default('private');
            $table->text('body');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['guest_id', 'visibility', 'archived_at'], 'guest_notes_guest_visibility_archive_idx');
            $table->index(['event_id', 'created_at'], 'guest_notes_event_created_idx');
        });

        Schema::create('guest_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('invitation_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->string('method', 24)->default('qr');
            $table->timestamp('checked_in_at', 6);
            $table->foreignId('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['event_id', 'guest_id'], 'guest_check_ins_event_guest_unique');
            $table->index(['event_id', 'checked_in_at'], 'guest_check_ins_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_check_ins');
        Schema::dropIfExists('guest_notes');
        Schema::dropIfExists('seat_assignments');
        Schema::dropIfExists('rsvps');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('guest_group_members');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('guest_groups');
    }
};
