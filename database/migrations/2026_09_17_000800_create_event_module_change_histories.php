<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_module_settings', function (Blueprint $table) {
            $table->string('origin', 30)->default('manual')->after('source');
        });

        DB::table('event_module_settings')->update(['origin' => DB::raw('source')]);

        Schema::create('event_module_change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('module_key', 64);
            $table->boolean('from_enabled');
            $table->boolean('to_enabled');
            $table->boolean('had_data')->default(false);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->dateTime('changed_at', 6);
            $table->foreign('module_key')->references('key')->on('module_definitions')->restrictOnDelete();
            $table->index(['event_id', 'changed_at'], 'event_module_history_event_time_idx');
            $table->index(['module_key', 'changed_at'], 'event_module_history_module_time_idx');
            $table->index(['actor_user_id', 'changed_at'], 'event_module_history_actor_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_module_change_histories');

        Schema::table('event_module_settings', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
