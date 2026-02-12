<?php

namespace App\Http\Controllers;

use App\Jobs\SyncYandexReviews;
use App\Models\YandexSetting;
use App\Services\YandexMapsParser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $setting = YandexSetting::where('user_id', auth()->id())->first();

        return Inertia::render('Settings', [
            'setting' => $setting,
        ]);
    }

    public function save(Request $request, YandexMapsParser $parser)
    {
        $request->validate([
            'maps_url' => ['required', 'url', 'regex:/yandex\.(ru|com)\/maps/'],
        ], [
            'maps_url.required' => 'Введите ссылку',
            'maps_url.url' => 'Введите корректную ссылку',
            'maps_url.regex' => 'Ссылка должна быть с Яндекс Карт',
        ]);

        $businessId = $parser->extractBusinessId($request->maps_url);

        if (! $businessId) {
            return back()->withErrors([
                'maps_url' => 'Не удалось извлечь ID организации. Проверьте формат ссылки.',
            ]);
        }

        YandexSetting::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'maps_url' => $request->maps_url,
                'business_id' => $businessId,
            ]
        );

        SyncYandexReviews::dispatch(auth()->id());

        return back()->with('success', 'Настройки сохранены. Синхронизация отзывов запущена.');
    }
}
