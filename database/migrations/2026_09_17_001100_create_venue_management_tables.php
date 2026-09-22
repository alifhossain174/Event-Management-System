<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->string('normalized_name', 180)->index();
            $table->string('type', 40)->default('third_party')->index();
            $table->string('status', 30)->default('active')->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('parking_capacity')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 120)->nullable()->index();
            $table->string('state_region', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->text('archive_reason')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'name']);
        });

        Schema::create('venue_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('code', 80)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_exclusive')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['venue_id', 'name']);
            $table->index(['venue_id', 'is_active']);
        });

        Schema::create('venue_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->text('details')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['venue_id', 'venue_space_id', 'is_active']);
        });

        Schema::create('venue_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 180);
            $table->string('rate_type', 80)->nullable();
            $table->decimal('amount', 19, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['venue_id', 'venue_space_id', 'is_active']);
        });

        Schema::create('seating_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->unsignedInteger('capacity')->nullable();
            $table->text('layout_notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['venue_id', 'venue_space_id', 'is_active']);
        });

        Schema::create('venue_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['venue_id', 'document_id']);
            $table->index(['venue_id', 'is_primary', 'sort_order']);
        });

        Schema::create('event_venue_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_space_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('planned')->index();
            $table->boolean('is_exclusive')->default(true);
            $table->decimal('quoted_price', 19, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('rate_type_snapshot', 80)->nullable();
            $table->unsignedInteger('capacity_snapshot')->nullable();
            $table->boolean('capacity_warning')->default(false)->index();
            $table->boolean('conflict_override')->default(false)->index();
            $table->text('conflict_override_reason')->nullable();
            $table->foreignId('conflict_override_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'venue_space_id', 'status'], 'venue_alloc_resource_status_idx');
            $table->index(['event_id', 'status']);
        });

        Schema::create('event_venue_allocation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_venue_allocation_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->index();
            $table->index(['event_venue_allocation_id', 'changed_at'], 'venue_alloc_history_time_idx');
            $table->foreign('event_venue_allocation_id', 'venue_alloc_history_allocation_fk')
                ->references('id')->on('event_venue_allocations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_venue_allocation_status_histories');
        Schema::dropIfExists('event_venue_allocations');
        Schema::dropIfExists('venue_media');
        Schema::dropIfExists('seating_plans');
        Schema::dropIfExists('venue_rates');
        Schema::dropIfExists('venue_facilities');
        Schema::dropIfExists('venue_spaces');
        Schema::dropIfExists('venues');
    }
};
