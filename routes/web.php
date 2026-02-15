<?php

use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ReviewsController::class, 'index'])->name('home');
    Route::get('/dashboard', [ReviewsController::class, 'index'])->name('dashboard');
    Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews');
    Route::post('/reviews/sync', [ReviewsController::class, 'sync'])->name('reviews.sync');
    Route::get('/yandex-settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/yandex-settings', [SettingsController::class, 'save'])->name('settings.save');
});
