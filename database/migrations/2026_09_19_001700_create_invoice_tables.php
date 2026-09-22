<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 64);
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('prefix', 32);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedTinyInteger('number_padding')->default(6);
            $table->timestamps(6);
            $table->unique(['scope_key', 'prefix', 'sequence_year'], 'invoice_sequences_scope_prefix_year_unique');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('credit_for_invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();
            $table->string('invoice_number', 96)->nullable()->unique();
            $table->string('document_type', 24)->default('invoice');
            $table->string('status', 24)->default('draft');
            $table->char('currency_code', 3);
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->string('subject', 191)->nullable();
            $table->text('notes')->nullable();
            $table->string('seller_name', 191)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_contact', 500)->nullable();
            $table->string('client_name', 191);
            $table->text('client_address')->nullable();
            $table->string('client_contact', 500)->nullable();
            $table->string('tax_label', 100)->default('Tax');
            $table->decimal('default_tax_rate', 9, 6)->default(0);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('taxable_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at', 6)->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('credited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('credited_at', 6)->nullable();
            $table->text('credit_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'status', 'due_date'], 'invoices_event_status_due_idx');
            $table->index(['client_id', 'status', 'due_date'], 'invoices_client_status_due_idx');
            $table->index(['branch_id', 'status', 'due_date'], 'invoices_branch_status_due_idx');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('line_subtotal', 19, 4);
            $table->string('discount_type', 16)->default('none');
            $table->decimal('discount_value', 19, 6)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('taxable_amount', 19, 4);
            $table->string('tax_label', 100)->default('Tax');
            $table->decimal('tax_rate', 9, 6)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps(6);
            $table->index(['invoice_id', 'sort_order'], 'invoice_items_invoice_order_idx');
            $table->index(['source_type', 'source_id'], 'invoice_items_source_idx');
        });

        Schema::create('invoice_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at', 6);
            $table->index(['invoice_id', 'changed_at'], 'invoice_status_history_invoice_changed_idx');
        });

        Schema::create('invoice_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('channel', 24)->default('email');
            $table->string('recipient', 320);
            $table->string('status', 24);
            $table->text('failure_message')->nullable();
            $table->foreignId('attempted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attempted_at', 6);
            $table->index(['invoice_id', 'status', 'attempted_at'], 'invoice_deliveries_invoice_status_idx');
        });

        foreach (['payments', 'payment_schedules', 'payment_allocations'] as $table) {
            if (DB::table($table)->whereNotNull('invoice_id')->whereNotExists(function ($query) use ($table) {
                $query->selectRaw('1')->from('invoices')->whereColumn('invoices.id', $table.'.invoice_id');
            })->exists()) {
                throw new RuntimeException("{$table} contains an invoice_id with no matching Invoice. Resolve it before applying Prompt 21.");
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreign('invoice_id', $table.'_invoice_id_foreign')->references('id')->on('invoices')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_allocations', 'payment_schedules', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign($table.'_invoice_id_foreign');
            });
        }

        Schema::dropIfExists('invoice_deliveries');
        Schema::dropIfExists('invoice_status_histories');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
