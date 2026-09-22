<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps(6);
            $table->index(['status', 'approved_at']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_budget_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('finance_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction', 20);
            $table->string('description', 255);
            $table->decimal('planned_amount', 19, 4);
            $table->char('currency_code', 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at', 6)->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps(6);
            $table->index(['event_id', 'direction', 'archived_at'], 'budget_line_event_direction_idx');
            $table->index(['event_budget_id', 'sort_order']);
            $table->index(['finance_category_id', 'direction']);
        });

        $this->createActualTable('event_income_entries', 'client_id', 'clients');
        $this->createActualTable('event_expense_entries', 'vendor_id', 'vendors');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_expense_entries');
        Schema::dropIfExists('event_income_entries');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('event_budgets');
    }

    private function createActualTable(string $name, string $partyColumn, string $partyTable): void
    {
        Schema::create($name, function (Blueprint $table) use ($name, $partyColumn, $partyTable) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('finance_category_id')->constrained()->restrictOnDelete();
            $table->foreignId($partyColumn)->nullable()->constrained($partyTable)->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->date('transaction_date');
            $table->string('description', 255);
            $table->string('status', 20)->default('draft');
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('evidence_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('posted_at', 6)->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at', 6)->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at', 6)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps(6);
            $table->foreign('reversal_of_id', $name.'_reversal_fk')->references('id')->on($name)->restrictOnDelete();
            $table->index(['event_id', 'status', 'transaction_date'], $name.'_event_status_date_idx');
            $table->index(['finance_category_id', 'status', 'transaction_date'], $name.'_category_status_date_idx');
            $table->index([$partyColumn, 'transaction_date'], $name.'_party_date_idx');
            $table->index(['reversal_of_id', 'status'], $name.'_reversal_status_idx');
            $table->unique(['event_id', 'source_type', 'source_id'], $name.'_source_unique');
        });
    }
};
