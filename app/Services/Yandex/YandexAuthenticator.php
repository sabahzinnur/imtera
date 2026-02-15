<?php

namespace App\Services\Yandex;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

class YandexAuthenticator
{
    private CookieJar $jar;

    private ?string $csrfToken = null;

    private ?string $businessName = null;

    private float $rating = 0.0;

    private int $votes = 0;

    public function __construct()
    {
        $this->jar = new CookieJar;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->jar;
    }

    public function getCookies(): array
    {
        return $this->jar->toArray();
    }

    public function setCookies(array $cookies): void
    {
        $this->jar = new CookieJar(false, $cookies);
    }

    public function getCsrfToken(): ?string
    {
        return $this->csrfToken;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function getVotes(): int
    {
        return $this->votes;
    }

    /**
     * Обновляет сессию и извлекает CSRF из HTML страницы организации.
     */
    public function refresh(Client $client, string $businessId): string
    {
        try {
            $response = $client->get("/maps/org/{$businessId}/reviews/");
            $html = (string) $response->getBody();

            // Извлекаем CSRF
            if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html, $m)) {
                $this->csrfToken = $m[1];
            }

            // Извлекаем название
            if (preg_match('/<h1[^>]*class="[^"]*orgpage-header-view__header[^"]*"[^>]*>(.*?)<\/h1>/', $html, $m)) {
                $this->businessName = trim(strip_tags($m[1]));
            }

            // Извлекаем рейтинг и кол-во голосов (как фолбек)
            if (preg_match('/"ratingValue"\s*:\s*"?([\d.]+)"?/', $html, $m)) {
                $this->rating = (float) $m[1];
            }
            if (preg_match('/"reviewCount"\s*:\s*"?(\d+)"?/', $html, $m)) {
                $this->votes = (int) $m[1];
            }

            if (! $this->csrfToken) {
                throw new \RuntimeException('CSRF token not found in Yandex HTML');
            }

            return $this->csrfToken;
        } catch (\Exception $e) {
            Log::error('Yandex Auth failed', ['businessId' => $businessId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
