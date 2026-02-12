<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    private Client $client;

    private CookieJar $jar;

    private const BASE_URL = 'https://yandex.ru';

    private const REVIEWS_EP = '/maps/api/business/fetchReviews';

    public function __construct()
    {
        $this->jar = new CookieJar;

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    .'AppleWebKit/537.36 (KHTML, like Gecko) '
                    .'Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => 'https://yandex.ru/maps/',
            ],
            'cookies' => $this->jar,
        ]);
    }

    // -------------------------------------------------------------------------
    // Публичные методы
    // -------------------------------------------------------------------------

    /**
     * Извлечь businessId из URL Яндекс Карт.
     *
     * Поддерживает форматы:
     *   https://yandex.ru/maps/org/название/1010501395/reviews/?...
     *   https://yandex.ru/maps/org/название/1010501395/
     *   https://yandex.ru/maps/?oid=1010501395
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

    /**
     * Загрузить все отзывы организации.
     *
     * @return array{reviews: list<array>, rating: float, total: int}
     */
    public function fetchAllReviews(string $businessId, int $maxPages = 10): array
    {
        Log::info('YandexMapsParser: start', ['businessId' => $businessId]);

        $csrfToken = $this->fetchCsrfTokenViaPost($businessId);

        if (! $csrfToken) {
            Log::error('YandexMapsParser: no CSRF token');
            throw new \RuntimeException('Не удалось получить CSRF-токен Яндекса');
        }

        Log::info('YandexMapsParser: got CSRF token', ['token' => $csrfToken]);

        $ts        = (int) round(microtime(true) * 1000);
        $sessionId = $ts.'_'.rand(100000, 999999);
        // reqId генерируем в формате близком к браузерному
        $reqId     = $ts.rand(100, 999).'-'.rand(100000000, 999999999).'-sas1-'.rand(1000, 9999);

        $allReviews = [];
        $rating     = 0.0;
        $total      = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            Log::info('YandexMapsParser: fetching page', ['page' => $page + 1]);

            try {
                $result = $this->fetchPage($businessId, $csrfToken, $sessionId, $reqId, $page);
            } catch (\Exception $e) {
                Log::error('YandexMapsParser: page failed', [
                    'page'  => $page + 1,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            if (empty($result['data']['reviews'])) {
                Log::info('YandexMapsParser: no reviews on page', ['page' => $page + 1]);
                break;
            }

            if ($page === 0) {
                $rating = (float) ($result['data']['businessRating']['score'] ?? 0);
                $total  = (int)   ($result['data']['businessRating']['votes'] ?? 0);
                Log::info('YandexMapsParser: meta', ['rating' => $rating, 'total' => $total]);
            }

            foreach ($result['data']['reviews'] as $r) {
                $allReviews[] = $this->mapReview($r);
            }

            $totalPages = (int) ($result['data']['pager']['total'] ?? 1);
            Log::info('YandexMapsParser: pager', ['page' => $page + 1, 'totalPages' => $totalPages]);

            if ($page + 1 >= $totalPages) {
                break;
            }

            usleep(900_000);
        }

        Log::info('YandexMapsParser: done', ['count' => count($allReviews)]);

        return [
            'reviews' => $allReviews,
            'rating'  => $rating,
            'total'   => $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    private function fetchCsrfTokenViaPost(string $businessId = null): ?string
    {
        try {
            // Заходим на страницу организации — получаем нужные cookie
            if ($businessId) {
                $this->client->get("/maps/org/{$businessId}/reviews/");
            }

            $response = $this->client->post(self::REVIEWS_EP);
            $data     = json_decode((string) $response->getBody(), true);

            if (! empty($data['csrfToken'])) {
                return $data['csrfToken'];
            }
        } catch (\Exception $e) {
            Log::error('YandexMapsParser: POST token failed', ['error' => $e->getMessage()]);
        }

        return $this->fetchCsrfTokenFromHtml($businessId);
    }

    private function fetchCsrfTokenFromHtml(string $businessId = null): ?string
    {
        try {
            $url      = $businessId ? "/maps/org/{$businessId}/reviews/" : '/maps/';
            $response = $this->client->get($url);
            $html     = (string) $response->getBody();

            foreach ([
                         '/"csrfToken"\s*:\s*"([^"]+)"/',
                         '/csrfToken["\s:=]+([a-f0-9:]+)/',
                     ] as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    return $m[1];
                }
            }
        } catch (\Exception $e) {
            Log::error('YandexMapsParser: HTML token failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Загрузить одну страницу отзывов.
     *
     * Параметры строго в алфавитном порядке ключей — критично для s_hash:
     * ajax < businessId < csrfToken < locale < page < pageSize < ranking < reqId < sessionId
     */
    private function fetchPage(
        string $businessId,
        string $csrfToken,
        string $sessionId,
        string $reqId,
        int $page
    ): array {
        $params = [
            'ajax'       => '1',
            'businessId' => $businessId,
            'csrfToken'  => $csrfToken,
            'locale'     => 'ru_RU',
            'page'       => (string) ($page + 1), // Яндекс считает страницы с 1
            'pageSize'   => '50',                  // максимум, снижает число запросов
            'ranking'    => 'by_time',
            'reqId'      => $reqId,                // ОБЯЗАТЕЛЕН — без него 500
            'sessionId'  => $sessionId,
        ];

        // Строка для хэша — ключи уже в алфавитном порядке
        $queryString = http_build_query($params);
        $s           = $this->computeSHash($queryString);

        Log::info('YandexMapsParser: s_hash input', ['qs' => $queryString, 's' => $s]);

        $response = $this->client->get(self::REVIEWS_EP, [
            'query'   => array_merge($params, ['s' => $s]),
            'headers' => [
                'Referer' => "https://yandex.ru/maps/org/{$businessId}/reviews/",
            ],
        ]);

        $body = (string) $response->getBody();
        Log::info('YandexMapsParser: response', ['body' => substr($body, 0, 500)]);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Невалидный JSON: '.substr($body, 0, 200));
        }

        if (! empty($data['csrfToken']) && ($data['statusCode'] ?? null) === 403) {
            throw new \RuntimeException('CSRF-токен устарел');
        }

        return $data;
    }

    /**
     * djb2 xor — точная копия JavaScript hashFunction из исходников Яндекса:
     *
     *   function hashFunction(e) {
     *       var t = e.length, n = 5381;
     *       for (var r = 0; r < t; r++) { n = (33 * n) ^ e.charCodeAt(r); }
     *       return n >>> 0;
     *   }
     */
    private function computeSHash(string $queryString): string
    {
        $n   = 5381;
        $len = strlen($queryString);

        for ($i = 0; $i < $len; $i++) {
            $n = ((33 * $n) ^ ord($queryString[$i])) & 0xFFFFFFFF;
        }

        // >>> 0 в JS = беззнаковое 32-бит целое
        return (string) ($n < 0 ? $n + 0x100000000 : $n);
    }

    private function mapReview(array $r): array
    {
        return [
            'yandex_review_id' => (string) ($r['id'] ?? uniqid('r_', true)),
            'author_name'      => $r['author']['name']       ?? 'Аноним',
            'author_phone'     => $r['author']['publicName'] ?? null,
            'branch_name'      => $r['branchName']            ?? null,
            'rating'           => (int) ($r['rating']         ?? 0),
            'text'             => $r['text']                   ?? null,
            'published_at'     => isset($r['updatedTime'])
                ? date('Y-m-d H:i:s', (int) $r['updatedTime'])
                : null,
        ];
    }
}
