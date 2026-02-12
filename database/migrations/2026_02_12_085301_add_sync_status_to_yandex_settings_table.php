<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('yandex_settings', function (Blueprint $table) {
            $table->string('sync_status')->default('pending'); // pending, syncing, completed, failed
            $table->text('sync_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yandex_settings', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'sync_error']);
        });
    }
};
