<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\YandexSettingsSaveRequest;
use App\Jobs\SyncYandexReviews;
use App\Services\YandexMapsParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index(): Response
    {
        return Inertia::render('Settings', [
            'setting' => auth()->user()->yandexSetting,
        ]);
    }

    /**
     * Save the Yandex Maps settings and trigger synchronization.
     */
    public function save(YandexSettingsSaveRequest $request, YandexMapsParser $parser): RedirectResponse
    {
        $user = auth()->user();
        $newUrl = $request->validated('maps_url');

        $businessId = $parser->extractBusinessId($newUrl);

        if (! $businessId) {
            throw ValidationException::withMessages([
                'maps_url' => __('Не удалось извлечь ID организации. Проверьте формат ссылки.'),
            ]);
        }

        // Удаляем все отзывы и сбрасываем настройки при смене URL
        \App\Models\Review::where('user_id', $user->id)->delete();

        $user->yandexSetting()->updateOrCreate(
            [],
            [
                'maps_url' => $newUrl,
                'business_id' => $businessId,
                'sync_status' => 'pending',
                'sync_page' => 0,
                'previous_sync_status' => null,
                'sync_error' => null,
                'last_synced_at' => null,
                'rating' => null,
                'reviews_count' => 0,
                'business_name' => null,
            ]
        );

        SyncYandexReviews::dispatch($user->id);

        return back()->with('success', __('Настройки сохранены. Синхронизация отзывов запущена.'));
    }
}
