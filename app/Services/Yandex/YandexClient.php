<?php

namespace App\Services\Yandex;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class YandexClient
{
    private Client $httpClient;
    private YandexSignatureGenerator $sigGen;
    private const BASE_URL = 'https://yandex.ru';
    private const REVIEWS_EP = '/maps/api/business/fetchReviews';

    public function __construct(YandexSignatureGenerator $sigGen, YandexAuthenticator $auth)
    {
        $this->sigGen = $sigGen;
        $this->httpClient = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 20,
            'cookies' => $auth->getCookieJar(),
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
    }

    public function fetchPage(string $businessId, string $csrfToken, int $page, string $sessionId, string $reqId): array
    {
        $params = [
            'ajax' => '1',
            'businessId' => $businessId,
            'csrfToken' => $csrfToken,
            'locale' => 'ru_RU',
            'page' => (string) ($page + 1),
            'pageSize' => '50',
            'ranking' => 'by_time',
            'reqId' => $reqId,
            'sessionId' => $sessionId,
        ];

        $queryString = http_build_query($params);
        $s = $this->sigGen->generate($queryString);

        try {
            $response = $this->httpClient->get(self::REVIEWS_EP, [
                'query' => array_merge($params, ['s' => $s]),
                'headers' => [
                    'Referer' => "https://yandex.ru/maps/org/{$businessId}/reviews/",
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (isset($data['error'])) {
                throw new \RuntimeException("Yandex API Error: " . ($data['error']['message'] ?? 'Unknown'));
            }

            // Проверка на протухший токен (Яндекс может вернуть 403 с новым CSRF в теле)
            if (($data['statusCode'] ?? null) === 403 || str_contains($data['message'] ?? '', 'csrf')) {
                throw new \App\Exceptions\YandexCsrfExpiredException();
            }

            return $data;
        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\YandexCsrfExpiredException) {
                throw $e;
            }
            Log::error('Yandex HTTP request failed', ['page' => $page, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getBaseClient(): Client
    {
        return $this->httpClient;
    }
}
