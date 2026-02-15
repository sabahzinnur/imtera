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
            $table->string('business_name')->nullable()->after('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yandex_settings', function (Blueprint $table) {
            $table->dropColumn('business_name');
        });
    }
};
