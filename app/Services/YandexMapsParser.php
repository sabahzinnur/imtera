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

    private float $extractedRating = 0.0;

    private int $extractedVotes = 0;

    private ?string $extractedBusinessName = null;

    public function __construct()
    {
        $this->jar = new CookieJar;

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => 'https://yandex.ru/maps/',
            ],
            'cookies' => $this->jar,
        ]);
    }

    public function getExtractedBusinessName(): ?string
    {
        return $this->extractedBusinessName;
    }

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

    /**
     * Загрузить отзывы организации.
     */
    public function fetchAllReviews(string $businessId, int $maxPages = 1000, ?string $lastReviewId = null, int $startPage = 0): array
    {
        Log::info('YandexMapsParser: start', [
            'businessId' => $businessId,
            'lastReviewId' => $lastReviewId,
            'startPage' => $startPage,
        ]);

        try {
            $csrfToken = $this->fetchCsrfToken($businessId);

            if (! $csrfToken) {
                throw new \RuntimeException('Не удалось получить CSRF-токен');
            }

            $ts = (int) round(microtime(true) * 1000);
            $sessionId = $ts.'_'.rand(100000, 999999);
            $reqId = $ts.rand(100, 999).'-'.rand(100000000, 999999999).'-sas1-'.rand(1000, 9999);

            $allReviews = [];
            $rating = $this->extractedRating;
            $total = $this->extractedVotes;
            $isAborted = false;

            for ($page = $startPage; $page < $maxPages; $page++) {
                try {
                    $result = $this->fetchPage($businessId, $csrfToken, $sessionId, $reqId, $page);
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), '403') || str_contains($e->getMessage(), '429')) {
                        $isAborted = true;
                        break;
                    }
                    throw $e;
                }

                if (empty($result['data']['reviews'])) {
                    break;
                }

                // Если в JSON есть данные о рейтинге, берем их (они точнее)
                if ($page === $startPage) {
                    if (isset($result['data']['businessRating']['score'])) {
                        $rating = (float) $result['data']['businessRating']['score'];
                    }
                    if (isset($result['data']['businessRating']['votes'])) {
                        $total = (int) $result['data']['businessRating']['votes'];
                    } elseif (isset($result['data']['params']['count'])) {
                        $total = (int) $result['data']['params']['count'];
                    }
                }

                $pageHasLastReview = false;
                foreach ($result['data']['reviews'] as $r) {
                    $mapped = $this->mapReview($r);
                    if ($lastReviewId && $mapped['yandex_review_id'] === $lastReviewId) {
                        $pageHasLastReview = true;
                        break;
                    }
                    $allReviews[] = $mapped;
                }

                if ($pageHasLastReview) {
                    break;
                }

                $totalPages = (int) ($result['data']['pager']['total'] ?? 1);
                if ($page + 1 >= $totalPages) {
                    break;
                }

                if ($page + 1 < $maxPages) {
                    sleep(5);
                }
            }

            return [
                'reviews' => $allReviews,
                'rating' => $rating,
                'total' => $total,
                'business_name' => $this->extractedBusinessName,
                'is_aborted' => $isAborted,
            ];

        } catch (\Exception $e) {
            Log::error('YandexMapsParser failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function fetchCsrfToken(string $businessId): ?string
    {
        try {
            // Заходим на страницу организации для установки кук
            $response = $this->client->get("/maps/org/{$businessId}/reviews/");
            $html = (string) $response->getBody();

            // Пытаемся вытащить название организации из HTML
            if (preg_match('/<h1[^>]*class="[^"]*orgpage-header-view__header[^"]*"[^>]*>(.*?)<\/h1>/', $html, $m)) {
                $this->extractedBusinessName = trim(strip_tags($m[1]));
            }

            // Пытаемся вытащить рейтинг из HTML
            if (preg_match('/"ratingValue"\s*:\s*"?([\d.]+)"?/', $html, $m)) {
                $this->extractedRating = (float) $m[1];
            }
            if (preg_match('/"reviewCount"\s*:\s*"?(\d+)"?/', $html, $m)) {
                $this->extractedVotes = (int) $m[1];
            }

            if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html, $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
            Log::error('YandexMapsParser: ошибка получения CSRF', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function prepareSession(): array
    {
        $ts = (int) round(microtime(true) * 1000);

        return [
            'sessionId' => $ts.'_'.rand(100000, 999999),
            'reqId' => $ts.rand(100, 999).'-'.rand(100000000, 999999999).'-sas1-'.rand(1000, 9999),
        ];
    }

    public function mapSinglePage(array $result): array
    {
        $reviews = [];
        foreach (($result['data']['reviews'] ?? []) as $r) {
            $reviews[] = $this->mapReview($r);
        }

        $totalReviews = (int) ($result['data']['businessRating']['votes'] ?? $result['data']['params']['count'] ?? 0);

        return [
            'reviews' => $reviews,
            'rating' => (float) ($result['data']['businessRating']['score'] ?? 0),
            'total' => $totalReviews,
            'totalPages' => (int) ceil($totalReviews / 50),
        ];
    }

    public function fetchPage(
        string $businessId,
        string $csrfToken,
        string $sessionId,
        string $reqId,
        int $page
    ): array {
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
        $s = $this->computeSHash($queryString);

        $response = $this->client->get(self::REVIEWS_EP, [
            'query' => array_merge($params, ['s' => $s]),
            'headers' => [
                'Referer' => "https://yandex.ru/maps/org/{$businessId}/reviews/",
            ],
        ]);

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Невалидный JSON');
        }

        if (! empty($data['error'])) {
            throw new \RuntimeException('Яндекс вернул ошибку: '.($data['error']['message'] ?? 'Unknown error'));
        }

        if (! empty($data['csrfToken']) && ($data['statusCode'] ?? null) === 403) {
            throw new \RuntimeException('CSRF-токен устарел');
        }

        return $data;
    }

    private function computeSHash(string $queryString): string
    {
        $n = 5381;
        $len = strlen($queryString);
        for ($i = 0; $i < $len; $i++) {
            $n = ((33 * $n) ^ ord($queryString[$i])) & 0xFFFFFFFF;
        }

        return (string) ($n < 0 ? $n + 0x100000000 : $n);
    }

    private function mapReview(array $r): array
    {
        $publishedAt = null;
        if (! empty($r['updatedTime'])) {
            $publishedAt = date('Y-m-d H:i:s', strtotime($r['updatedTime']));
        }

        return [
            'yandex_review_id' => (string) ($r['reviewId'] ?? $r['id'] ?? uniqid('r_', true)),
            'author_name' => $r['author']['name'] ?? 'Аноним',
            'author_phone' => null, // Яндекс обычно не дает телефон. publicName - это не телефон.
            'branch_name' => $r['branchName'] ?? $r['org']['name'] ?? $r['business']['name'] ?? $this->extractedBusinessName,
            'rating' => (int) ($r['rating'] ?? 0),
            'text' => $r['text'] ?? null,
            'published_at' => $publishedAt,
        ];
    }
}
