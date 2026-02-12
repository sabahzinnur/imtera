<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('yandex_review_id')->index();
            $table->string('author_name');
            $table->string('author_phone')->nullable();
            $table->string('branch_name')->nullable();       // "Филиал 1"
            $table->tinyInteger('rating');                   // 1–5
            $table->text('text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'yandex_review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
