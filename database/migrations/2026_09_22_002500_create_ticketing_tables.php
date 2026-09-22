<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('ticketing_public_slug', 64)->nullable()->unique()->after('archive_reason');
            $table->timestamp('ticketing_published_at')->nullable()->index()->after('ticketing_public_slug');
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->string('category', 32)->default('custom');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity_total');
            $table->decimal('price', 19, 4)->default(0);
            $table->char('currency_code', 3);
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->string('status', 24)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'code']);
            $table->index(['event_id', 'status', 'is_public'], 'ticket_types_event_state_idx');
            $table->index(['event_id', 'sale_starts_at', 'sale_ends_at'], 'ticket_types_event_window_idx');
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type', 16);
            $table->decimal('discount_value', 19, 4);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'code']);
            $table->index(['event_id', 'is_active', 'valid_from', 'valid_until'], 'promo_codes_event_validity_idx');
        });

        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('source', 24)->default('manual');
            $table->string('status', 32)->default('issued');
            $table->string('attendee_name');
            $table->string('attendee_email', 320)->nullable();
            $table->string('attendee_phone', 50)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_snapshot', 19, 4);
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->char('currency_code', 3);
            $table->json('promo_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status', 'issued_at'], 'ticket_orders_event_status_time_idx');
            $table->index(['event_id', 'attendee_email'], 'ticket_orders_event_email_idx');
            $table->index(['ticket_type_id', 'status'], 'ticket_orders_type_status_idx');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number', 48)->unique();
            $table->string('attendee_name');
            $table->string('attendee_email', 320)->nullable();
            $table->string('attendee_phone', 50)->nullable();
            $table->decimal('price_snapshot', 19, 4);
            $table->decimal('discount_snapshot', 19, 4)->default(0);
            $table->decimal('final_price_snapshot', 19, 4);
            $table->char('currency_code', 3);
            $table->string('status', 24)->default('issued');
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->timestamp('issued_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status', 'issued_at'], 'tickets_event_status_time_idx');
            $table->index(['event_id', 'attendee_email'], 'tickets_event_email_idx');
            $table->index(['ticket_type_id', 'status'], 'tickets_type_status_idx');
        });

        Schema::create('ticket_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at');
            $table->string('method', 24)->default('qr');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['event_id', 'validated_at'], 'ticket_validations_event_time_idx');
        });

        Schema::create('ticket_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('financial_refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->string('refund_number', 48)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->string('method', 32);
            $table->string('reason', 500);
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at');
            $table->timestamps();
            $table->index(['event_id', 'refunded_at'], 'ticket_refunds_event_time_idx');
        });

        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->foreignId('ticket_order_id')->nullable()->after('registration_id')->constrained('ticket_orders')->restrictOnDelete();
            $table->index(['ticket_order_id', 'created_at'], 'outbound_messages_ticket_order_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->dropForeign(['ticket_order_id']);
            $table->dropIndex('outbound_messages_ticket_order_time_idx');
            $table->dropColumn('ticket_order_id');
        });
        Schema::dropIfExists('ticket_refunds');
        Schema::dropIfExists('ticket_validations');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_orders');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('ticket_types');
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['ticketing_public_slug']);
            $table->dropIndex(['ticketing_published_at']);
            $table->dropColumn(['ticketing_public_slug', 'ticketing_published_at']);
        });
    }
};
