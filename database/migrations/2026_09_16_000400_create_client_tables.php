<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['individual', 'organization']);
            $table->string('display_name', 180);
            $table->string('normalized_name', 180)->index();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('organization_name', 180)->nullable();
            $table->string('legal_name', 180)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_identifier', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('primary_email', 255)->nullable();
            $table->string('normalized_email', 255)->nullable()->index();
            $table->string('primary_phone', 60)->nullable();
            $table->string('normalized_phone', 40)->nullable()->index();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state_region', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('archived_at', 6)->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->foreignId('merged_into_client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'display_name']);
            $table->index(['type', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('relationship_label', 100)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('normalized_email', 255)->nullable()->index();
            $table->string('phone', 60)->nullable();
            $table->string('normalized_phone', 40)->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'is_primary']);
            $table->index(['client_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
    }
};
