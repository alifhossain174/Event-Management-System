<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['is_active', 'sort_order', 'name']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name', 180);
            $table->string('normalized_name', 180)->index();
            $table->string('legal_name', 180)->nullable();
            $table->string('primary_email')->nullable();
            $table->string('normalized_email')->nullable()->index();
            $table->string('primary_phone', 60)->nullable();
            $table->string('normalized_phone', 40)->nullable()->index();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('archived_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'display_name']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('vendor_category_vendor', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_category_id')->constrained()->restrictOnDelete();
            $table->primary(['vendor_id', 'vendor_category_id']);
        });

        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('job_title', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('normalized_email')->nullable()->index();
            $table->string('phone', 60)->nullable();
            $table->string('normalized_phone', 40)->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vendor_id', 'is_primary']);
        });

        Schema::create('vendor_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('normalized_name', 180)->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 180);
            $table->string('normalized_name', 180)->index();
            $table->string('email')->nullable();
            $table->string('normalized_email')->nullable()->index();
            $table->string('phone', 60)->nullable();
            $table->string('normalized_phone', 40)->nullable()->index();
            $table->string('role_title', 150)->nullable();
            $table->string('employment_status', 30)->default('active')->index();
            $table->text('default_availability_notes')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('record_status', 30)->default('active');
            $table->timestamp('archived_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['record_status', 'display_name']);
            $table->index(['branch_id', 'record_status']);
            $table->index(['department_id', 'record_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('vendor_service_areas');
        Schema::dropIfExists('vendor_contacts');
        Schema::dropIfExists('vendor_category_vendor');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('departments');
    }
};
