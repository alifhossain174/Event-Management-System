<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreignId('income_entry_id')->nullable()->unique()->constrained('event_income_entries')->restrictOnDelete();
            $table->string('receipt_number', 64)->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('payment_type', 24);
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->timestamp('received_at', 6);
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('channel', 100)->nullable();
            $table->string('external_reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'received_at'], 'payments_event_status_received_idx');
            $table->index(['client_id', 'status', 'received_at'], 'payments_client_status_received_idx');
            $table->index(['invoice_id', 'status'], 'payments_invoice_status_idx');
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('label', 191);
            $table->decimal('amount_due', 19, 4);
            $table->char('currency_code', 3);
            $table->date('due_date');
            $table->string('status', 24)->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'due_date'], 'payment_schedules_event_due_idx');
            $table->index(['client_id', 'status', 'due_date'], 'payment_schedules_client_due_idx');
            $table->index(['invoice_id', 'status'], 'payment_schedules_invoice_idx');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('amount', 19, 4);
            $table->timestamp('allocated_at', 6);
            $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['payment_id', 'payment_schedule_id'], 'payment_allocations_payment_schedule_idx');
            $table->index(['invoice_id', 'allocated_at'], 'payment_allocations_invoice_idx');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('refund_number', 64)->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->timestamp('refunded_at', 6);
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('posted');
            $table->string('channel', 100)->nullable();
            $table->string('external_reference', 191)->nullable();
            $table->text('reason');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['payment_id', 'status', 'refunded_at'], 'refunds_payment_status_refunded_idx');
            $table->index(['event_id', 'status', 'refunded_at'], 'refunds_event_status_refunded_idx');
            $table->index(['client_id', 'status', 'refunded_at'], 'refunds_client_status_refunded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('payments');
    }
};
