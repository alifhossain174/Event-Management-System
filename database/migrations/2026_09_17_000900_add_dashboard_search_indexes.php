<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('name', 'events_name_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'users_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_name_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_idx');
        });
    }
};
