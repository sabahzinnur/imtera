<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    private Client $client;

    private CookieJar $jar;

    public function __construct()
    {
        $this->jar = new CookieJar;
        $this->client = new Client([
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'Referer' => 'https://yandex.ru/maps/',
            ],
            'cookies' => $this->jar,
        ]);
    }

    /**
     * Извлечь businessId из URL
     * Поддерживает: https://yandex.ru/maps/org/name/1010501395/reviews/
     */
    public function extractBusinessId(string $url): ?string
    {
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $m)) {
            return $m[1];
        }
        if (preg_match('/[?&]oid=(\d+)/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * s_hash — специальная хэш-функция Яндекса (djb2-based)
     */
    private function sHash(string $str): string
    {
        $h = 5381;
        for ($i = 0; $i < strlen($str); $i++) {
            $h = (($h << 5) + $h + ord($str[$i])) & 0xFFFFFFFF;
        }

        return (string) (($h + 0x100000000) % 0x100000000);
    }

    /**
     * Получить CSRF-токен (первый запрос устанавливает cookie)
     */
    private function fetchCsrfToken(string $businessId): ?string
    {
        try {
            $response = $this->client->get("https://yandex.ru/maps/org/{$businessId}/reviews/");
            $html = (string) $response->getBody();

            if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $html, $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
            Log::error('YandexMapsParser: cannot get csrf', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Загрузить все отзывы (до $maxPages страниц по 10)
     *
     * @return array{reviews: array, rating: float, total: int}
     */
    public function fetchAllReviews(string $businessId, int $maxPages = 10): array
    {
        $csrfToken = $this->fetchCsrfToken($businessId);
        if (! $csrfToken) {
            throw new \RuntimeException('Не удалось получить CSRF-токен Яндекса');
        }

        $sessionId = round(microtime(true) * 1000).'_'.rand(100000, 999999);
        $allReviews = [];
        $rating = 0;
        $total = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $params = http_build_query([
                'ajax' => 1,
                'businessId' => $businessId,
                'csrfToken' => $csrfToken,
                'page' => $page,
                'pageSize' => 10,
                'ranking' => 'by_time',
                'sessionId' => $sessionId,
            ]);

            $s = $this->sHash($params);
            $url = "https://yandex.ru/maps/api/business/fetchReviews?{$params}&s={$s}";

            try {
                $response = $this->client->get($url);
                $data = json_decode((string) $response->getBody(), true);
            } catch (\Exception $e) {
                Log::error('YandexMapsParser: fetch error', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }

            if (empty($data['data']['reviews'])) {
                break;
            }

            if ($page === 0) {
                $rating = $data['data']['businessRating']['score'] ?? 0;
                $total = $data['data']['businessRating']['votes'] ?? 0;
            }

            foreach ($data['data']['reviews'] as $r) {
                $allReviews[] = [
                    'yandex_review_id' => $r['id'] ?? uniqid(),
                    'author_name' => $r['author']['name'] ?? 'Аноним',
                    'author_phone' => $r['author']['publicName'] ?? null,
                    'branch_name' => $r['branchName'] ?? null,
                    'rating' => (int) ($r['rating'] ?? 0),
                    'text' => $r['text'] ?? null,
                    'published_at' => isset($r['updatedTime'])
                                            ? date('Y-m-d H:i:s', $r['updatedTime'])
                                            : null,
                ];
            }

            $pagesTotal = $data['data']['pager']['total'] ?? 1;
            if ($page + 1 >= $pagesTotal) {
                break;
            }

            usleep(800000); // пауза 0.8 сек между запросами
        }

        return [
            'reviews' => $allReviews,
            'rating' => (float) $rating,
            'total' => (int) $total,
        ];
    }
}
