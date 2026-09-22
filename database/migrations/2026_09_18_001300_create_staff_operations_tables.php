<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->string('status', 30)->default('scheduled');
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('conflict_overridden')->default(false);
            $table->text('conflict_override_reason')->nullable();
            $table->foreignId('conflict_overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at', 6)->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['staff_profile_id', 'starts_at', 'ends_at'], 'shift_staff_window_idx');
            $table->index(['event_id', 'status']);
            $table->index(['staff_profile_id', 'status']);
        });

        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role_title', 180);
            $table->text('responsibilities')->nullable();
            $table->dateTime('scheduled_starts_at', 6);
            $table->dateTime('scheduled_ends_at', 6);
            $table->string('status', 30)->default('planned');
            $table->foreignId('responsible_manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('conflict_overridden')->default(false);
            $table->text('conflict_override_reason')->nullable();
            $table->json('conflict_details')->nullable();
            $table->foreignId('conflict_overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['event_id', 'status']);
            $table->index(['staff_profile_id', 'scheduled_starts_at', 'scheduled_ends_at'], 'staff_assignment_window_idx');
            $table->index(['staff_profile_id', 'status']);
            $table->index(['responsible_manager_user_id', 'status'], 'staff_assignment_manager_idx');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('staff_assignment_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->dateTime('clocked_in_at', 6)->nullable();
            $table->dateTime('clocked_out_at', 6)->nullable();
            $table->string('status', 30);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['staff_profile_id', 'attendance_date']);
            $table->index(['event_id', 'attendance_date']);
            $table->index(['staff_assignment_id', 'attendance_date'], 'attendance_assignment_date_idx');
            $table->index(['attendance_date', 'status']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('leave_type', 80);
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps(6);
            $table->index(['staff_profile_id', 'starts_on', 'ends_on'], 'staff_leave_window_idx');
            $table->index(['status', 'starts_on']);
        });

        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->decimal('amount', 19, 4);
            $table->char('currency_code', 3);
            $table->string('payment_status', 30)->default('due');
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);
            $table->index(['staff_profile_id', 'period_starts_on', 'period_ends_on'], 'salary_staff_period_idx');
            $table->index(['payment_status', 'period_ends_on']);
        });

        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('summary');
            $table->text('strengths')->nullable();
            $table->text('improvement_notes')->nullable();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at', 6);
            $table->timestamps(6);
            $table->index(['staff_profile_id', 'reviewed_at']);
            $table->index(['event_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_records');
        Schema::dropIfExists('salary_records');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('shifts');
    }
};
