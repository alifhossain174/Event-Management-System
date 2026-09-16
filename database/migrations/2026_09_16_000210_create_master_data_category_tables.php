<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCategoryTable('event_categories');
        $this->createCategoryTable('vendor_categories');
        $this->createCategoryTable('inventory_categories');
        $this->createCategoryTable('finance_categories', function (Blueprint $table) {
            $table->string('direction', 20)->default('both')->index();
        });
        $this->createCategoryTable('document_categories');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('vendor_categories');
        Schema::dropIfExists('event_categories');
    }

    private function createCategoryTable(string $name, ?callable $extra = null): void
    {
        Schema::create($name, function (Blueprint $table) use ($extra) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $extra?->__invoke($table);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['is_active', 'sort_order', 'name']);
        });
    }
};
