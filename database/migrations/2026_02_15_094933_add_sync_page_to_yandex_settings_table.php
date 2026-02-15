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
            $table->unsignedInteger('sync_page')->default(0)->after('sync_status');
            $table->string('previous_sync_status')->nullable()->after('sync_page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yandex_settings', function (Blueprint $table) {
            $table->dropColumn(['sync_page', 'previous_sync_status']);
        });
    }
};
