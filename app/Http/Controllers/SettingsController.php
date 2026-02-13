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
        $businessId = $parser->extractBusinessId($request->validated('maps_url'));

        if (! $businessId) {
            throw ValidationException::withMessages([
                'maps_url' => __('Не удалось извлечь ID организации. Проверьте формат ссылки.'),
            ]);
        }

        auth()->user()->yandexSetting()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'maps_url' => $request->validated('maps_url'),
                'business_id' => $businessId,
                'sync_status' => 'pending',
                'sync_error' => null,
            ]
        );

        SyncYandexReviews::dispatch(auth()->id());

        return back()->with('success', __('Настройки сохранены. Синхронизация отзывов запущена.'));
    }
}
