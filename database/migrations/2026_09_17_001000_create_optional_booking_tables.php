<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_event_category_id')->constrained('event_categories')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->unique()->constrained('events')->restrictOnDelete();
            $table->string('status', 30)->default('enquiry');
            $table->dateTime('requested_starts_at', 6);
            $table->dateTime('requested_ends_at', 6);
            $table->string('timezone', 64);
            $table->string('venue_preference', 255)->nullable();
            $table->unsignedInteger('expected_guest_count')->nullable();
            $table->decimal('budget_estimate', 19, 4)->nullable();
            $table->char('currency_code', 3);
            $table->text('notes')->nullable();
            $table->dateTime('approved_at', 6)->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('rescheduled_at', 6)->nullable();
            $table->foreignId('rescheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('financial_review_required')->default(false);
            $table->json('financial_flags')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['status', 'requested_starts_at']);
            $table->index(['client_id', 'status']);
            $table->index(['branch_id', 'status', 'requested_starts_at'], 'bookings_branch_status_start_idx');
            $table->index(['requested_event_category_id', 'requested_starts_at'], 'bookings_category_start_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['booking_id']);
            $table->unique('booking_id');
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('actor_type', 20)->default('user');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('changed_at', 6);
            $table->index(['booking_id', 'changed_at']);
            $table->index(['actor_user_id', 'changed_at']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('status', 30)->default('active');
            $table->text('reason')->nullable();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('added_at', 6);
            $table->foreignId('promoted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('promoted_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['status', 'position']);
        });

        Schema::create('booking_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('conflicts')->nullable();
            $table->boolean('conflict_override')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at', 6);
            $table->index(['booking_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_changes');
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('booking_status_histories');

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
            $table->index('booking_id');
        });

        Schema::dropIfExists('bookings');
    }
};
