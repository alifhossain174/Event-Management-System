<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 150);
            $table->string('scope', 30)->index();
            $table->boolean('is_event_scoped')->default(false)->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps(6);
            $table->index(['is_event_scoped', 'is_active', 'sort_order'], 'module_definitions_toggle_active_order_idx');
        });

        Schema::create('event_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_template_id')->nullable()->constrained('event_templates')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('slug', 190)->unique();
            $table->text('description')->nullable();
            $table->text('service_notes')->nullable();
            $table->json('starter_tasks')->nullable();
            $table->json('budget_lines')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('archived_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['status', 'name']);
            $table->index(['event_category_id', 'status']);
        });

        Schema::create('event_template_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_template_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->string('recommendation', 20)->default('default');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps(6);
            $table->foreign('module_key')->references('key')->on('module_definitions')->restrictOnDelete();
            $table->unique(['event_template_id', 'module_key'], 'event_template_module_unique');
            $table->index(['module_key', 'recommendation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_modules');
        Schema::dropIfExists('event_templates');
        Schema::dropIfExists('module_definitions');
    }
};
