<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('availability_conflict_policy', 20)->default('warn')->after('status');
        });

        Schema::create('vendor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->string('status', 20)->default('unavailable');
            $table->string('conflict_action', 20)->default('warn');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['vendor_id', 'starts_at', 'ends_at'], 'vendor_availability_window_idx');
            $table->index(['vendor_id', 'status', 'conflict_action'], 'vendor_availability_rule_idx');
        });

        Schema::create('vendor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_category_id')->constrained()->restrictOnDelete();
            $table->text('scope');
            $table->dateTime('scheduled_starts_at', 6);
            $table->dateTime('scheduled_ends_at', 6);
            $table->decimal('quoted_cost', 19, 4)->nullable();
            $table->decimal('approved_cost', 19, 4)->nullable();
            $table->char('currency_code', 3);
            $table->string('status', 30)->default('draft');
            $table->string('delivery_status', 30)->default('pending');
            $table->foreignId('responsible_manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('delivery_notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('availability_warning')->default(false);
            $table->json('availability_warning_details')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['event_id', 'status']);
            $table->index(['vendor_id', 'scheduled_starts_at', 'scheduled_ends_at'], 'vendor_assignment_window_idx');
            $table->index(['vendor_id', 'status']);
            $table->index(['responsible_manager_user_id', 'status'], 'vendor_assignment_manager_status_idx');
        });

        Schema::create('vendor_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_assignment_id')->constrained()->restrictOnDelete();
            $table->string('reference_number', 50)->unique();
            $table->string('title', 180);
            $table->text('instructions');
            $table->text('deliverables')->nullable();
            $table->dateTime('due_at', 6)->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['vendor_assignment_id', 'status']);
            $table->index(['due_at', 'status']);
        });

        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->string('contract_reference', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['vendor_assignment_id', 'status']);
            $table->index(['expiry_date', 'status']);
        });

        Schema::create('vendor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('comments')->nullable();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps(6);
            $table->index(['vendor_id', 'score']);
        });

        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->string('status', 30)->default('received');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['vendor_assignment_id', 'status']);
            $table->index(['vendor_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoices');
        Schema::dropIfExists('vendor_ratings');
        Schema::dropIfExists('vendor_contracts');
        Schema::dropIfExists('vendor_work_orders');
        Schema::dropIfExists('vendor_assignments');
        Schema::dropIfExists('vendor_availabilities');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('availability_conflict_policy');
        });
    }
};
