<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index()->after('password');
            $table->timestamp('deactivated_at')->nullable()->index()->after('is_active');
            $table->foreignId('deactivated_by_user_id')->nullable()->after('deactivated_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('deactivated_by_user_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by_user_id']);
            $table->dropColumn([
                'is_active', 'deactivated_at', 'deactivated_by_user_id', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
