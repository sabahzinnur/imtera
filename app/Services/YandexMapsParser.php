<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    private Client $client;
    private CookieJar $jar;

    private const BASE_URL   = 'https://yandex.ru';
    private const REVIEWS_EP = '/maps/api/business/fetchReviews';

    public function __construct()
    {
        $this->jar = new CookieJar();

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout'  => 20,
            'headers'  => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                    . 'Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'X-Requested-With'=> 'XMLHttpRequest',
                'Referer'         => 'https://yandex.ru/maps/',
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
        // /org/{slug}/{id}/
        if (preg_match('/\/org\/[^\/]+\/(\d{5,})/', $url, $m)) {
            return $m[1];
        }
        // ?oid=...
        if (preg_match('/[?&]oid=(\d+)/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Загрузить все отзывы организации (до $maxPages × 10 штук).
     *
     * @return array{
     *   reviews: list<array{
     *     yandex_review_id: string,
     *     author_name: string,
     *     author_phone: string|null,
     *     branch_name: string|null,
     *     rating: int,
     *     text: string|null,
     *     published_at: string|null
     *   }>,
     *   rating: float,
     *   total: int
     * }
     */
    public function fetchAllReviews(string $businessId, int $maxPages = 10): array
    {
        // Шаг 1: получаем csrfToken через пустой POST (надёжный способ)
        $csrfToken = $this->fetchCsrfTokenViaPost();

        if (!$csrfToken) {
            throw new \RuntimeException('Не удалось получить CSRF-токен Яндекса');
        }

        // Генерируем идентификаторы сессии один раз на всю серию запросов
        $ts        = (int) round(microtime(true) * 1000);
        $sessionId = $ts . '_' . rand(100000, 999999);
        $reqId     = $ts . rand(100, 999) . '-' . rand(100000000, 999999999);

        $allReviews = [];
        $rating     = 0.0;
        $total      = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                $result = $this->fetchPage(
                    $businessId, $csrfToken, $sessionId, $reqId, $page
                );
            } catch (\Exception $e) {
                Log::error('YandexMapsParser: страница не загружена', [
                    'businessId' => $businessId,
                    'page'       => $page,
                    'error'      => $e->getMessage(),
                ]);
                break;
            }

            // Нет отзывов на странице — закончили
            if (empty($result['data']['reviews'])) {
                break;
            }

            // Метаданные берём только с первой страницы
            if ($page === 0) {
                $rating = (float) ($result['data']['businessRating']['score'] ?? 0);
                $total  = (int)   ($result['data']['businessRating']['votes'] ?? 0);
            }

            foreach ($result['data']['reviews'] as $r) {
                $allReviews[] = $this->mapReview($r);
            }

            // Проверяем, есть ли следующая страница
            $totalPages = (int) ($result['data']['pager']['total'] ?? 1);
            if ($page + 1 >= $totalPages) {
                break;
            }

            // Пауза между запросами — не торопимся
            usleep(900_000); // 0.9 сек
        }

        return [
            'reviews' => $allReviews,
            'rating'  => $rating,
            'total'   => $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    /**
     * Получить csrfToken через пустой POST-запрос на endpoint.
     *
     * Яндекс при любом запросе без корректного токена возвращает 200 с JSON:
     * {"csrfToken": "...", "statusCode": 403}
     *
     * Этот токен затем принимается в следующем запросе.
     */
    private function fetchCsrfTokenViaPost(): ?string
    {
        try {
            $response = $this->client->post(self::REVIEWS_EP);
            $data = json_decode((string) $response->getBody(), true);

            if (!empty($data['csrfToken'])) {
                return $data['csrfToken'];
            }
        } catch (\Exception $e) {
            Log::error('YandexMapsParser: POST за токеном упал', [
                'error' => $e->getMessage(),
            ]);
        }

        // Запасной план: вытащить из HTML страницы
        return $this->fetchCsrfTokenFromHtml();
    }

    /**
     * Запасной способ: загрузить HTML и вытащить csrfToken регуляркой.
     */
    private function fetchCsrfTokenFromHtml(): ?string
    {
        try {
            $response = $this->client->get('/maps/');
            $html     = (string) $response->getBody();

            // window.__REDUX_STATE__ или data-attribute
            foreach ([
                         '/"csrfToken"\s*:\s*"([^"]+)"/',
                         '/csrfToken["\s:=]+([a-f0-9:]+)/',
                     ] as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    return $m[1];
                }
            }
        } catch (\Exception $e) {
            Log::error('YandexMapsParser: HTML fallback упал', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Загрузить одну страницу отзывов.
     */
    private function fetchPage(
        string $businessId,
        string $csrfToken,
        string $sessionId,
        string $reqId,
        int    $page
    ): array {
        // ВАЖНО: параметры должны идти в алфавитном порядке ключей,
        // именно из этой строки считается s_hash.
        $params = [
            'ajax'       => '1',
            'businessId' => $businessId,
            'csrfToken'  => $csrfToken,
            'locale'     => 'ru_RU',
            'page'       => (string) $page,
            'pageSize'   => '10',
            'ranking'    => 'by_time',   // by_time | by_rating
            'reqId'      => $reqId,
            'sessionId'  => $sessionId,
        ];

        // Строка для хэша — ключи уже отсортированы алфавитно в массиве выше
        $queryString = http_build_query($params);
        $s           = $this->computeSHash($queryString);

        $response = $this->client->get(self::REVIEWS_EP, [
            'query' => array_merge($params, ['s' => $s]),
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Яндекс вернул невалидный JSON');
        }

        // Если токен протух — Яндекс вернёт новый в теле ответа
        if (!empty($data['csrfToken']) && isset($data['statusCode']) && $data['statusCode'] === 403) {
            throw new \RuntimeException('CSRF-токен устарел, нужно обновить');
        }

        return $data;
    }

    /**
     * Алгоритм s_hash — djb2 xor, беззнаковый 32-бит.
     *
     * JavaScript-оригинал:
     *   function hashFunction(e) {
     *       var t = e.length, n = 5381;
     *       for (var r = 0; r < t; r++) { n = (33 * n) ^ e.charCodeAt(r); }
     *       return n >>> 0;
     *   }
     */
    private function computeSHash(string $queryString): string
    {
        $n = 5381;
        $len = strlen($queryString);

        for ($i = 0; $i < $len; $i++) {
            // (33 * n) ^ charCode — потом усекаем до 32 бит
            $n = ((33 * $n) ^ ord($queryString[$i])) & 0xFFFFFFFF;
        }

        // Аналог >>> 0 в JS (беззнаковый сдвиг = беззнаковое 32-бит целое)
        return (string) ($n < 0 ? $n + 0x100000000 : $n);
    }

    /**
     * Нормализовать один отзыв из JSON в плоский массив для БД.
     */
    private function mapReview(array $r): array
    {
        return [
            'yandex_review_id' => (string) ($r['id']               ?? uniqid('r_', true)),
            'author_name'      => $r['author']['name']              ?? 'Аноним',
            'author_phone'     => $r['author']['publicName']        ?? null,
            'branch_name'      => $r['branchName']                  ?? null,
            'rating'           => (int) ($r['rating']               ?? 0),
            'text'             => $r['text']                         ?? null,
            'published_at'     => isset($r['updatedTime'])
                ? date('Y-m-d H:i:s', (int) $r['updatedTime'])
                : null,
        ];
    }
}
