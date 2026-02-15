<?php

namespace App\Services;

use App\Exceptions\YandexCsrfExpiredException;
use App\Services\Yandex\YandexAuthenticator;
use App\Services\Yandex\YandexClient;
use App\Services\Yandex\YandexReviewMapper;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    public function __construct(
        private YandexAuthenticator $auth,
        private YandexClient $client,
        private YandexReviewMapper $mapper
    ) {}

    /**
     * Извлечь businessId из URL Яндекс Карт.
     */
    public function extractBusinessId(string $url): ?string
    {
        if (preg_match('/\/org\/[^\/]+\/(\d{5,})/', $url, $m)) {
            return $m[1];
        }
        if (preg_match('/[?&]oid=(\d+)/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function fetchPageWithRetry(string $businessId, ?string $csrfToken, int $page, string $sessionId, string $reqId, array $cookies = []): array
    {
        if (! empty($cookies)) {
            $this->auth->setCookies($cookies);
        }

        $token = $csrfToken;

        try {
            // Если токена нет вовсе (первый запуск), получаем его
            if (! $token) {
                $token = $this->auth->refresh($this->client->getBaseClient(), $businessId);
            }

            $raw = $this->client->fetchPage($businessId, $token, $page, $sessionId, $reqId);

            return [
                'data' => $this->mapper->mapPage($raw, $this->auth->getBusinessName()),
                'csrfToken' => $token,
                'cookies' => $this->auth->getCookies(),
                'businessName' => $this->auth->getBusinessName(),
                'rating' => $this->auth->getRating(),
                'votes' => $this->auth->getVotes(),
            ];

        } catch (YandexCsrfExpiredException $e) {
            Log::warning('Yandex CSRF expired, refreshing...', ['businessId' => $businessId]);

            // Пробуем обновить токен и повторить запрос
            $token = $this->auth->refresh($this->client->getBaseClient(), $businessId);
            $raw = $this->client->fetchPage($businessId, $token, $page, $sessionId, $reqId);

            return [
                'data' => $this->mapper->mapPage($raw, $this->auth->getBusinessName()),
                'csrfToken' => $token,
                'cookies' => $this->auth->getCookies(),
                'businessName' => $this->auth->getBusinessName(),
                'rating' => $this->auth->getRating(),
                'votes' => $this->auth->getVotes(),
            ];
        }
    }

    /**
     * Хелпер для подготовки сессии (для Job)
     */
    public function prepareSession(): array
    {
        $ts = (int) round(microtime(true) * 1000);

        return [
            'sessionId' => $ts.'_'.rand(100000, 999999),
            'reqId' => $ts.rand(100, 999).'-'.rand(100000000, 999999999).'-sas1-'.rand(1000, 9999),
        ];
    }
}
