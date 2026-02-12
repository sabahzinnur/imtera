<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('maps_url', 1000)->nullable();    // вставленная ссылка
            $table->string('business_id')->nullable();       // извлечённый ID организации
            $table->decimal('rating', 3, 2)->nullable();     // средний рейтинг
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_settings');
    }
};
