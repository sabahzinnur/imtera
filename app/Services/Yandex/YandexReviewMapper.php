<?php

namespace App\Services\Yandex;

class YandexReviewMapper
{
    /**
     * Преобразует ответ страницы в структурированный массив.
     */
    public function mapPage(array $data, ?string $fallbackBusinessName = null): array
    {
        $reviews = [];
        foreach (($data['data']['reviews'] ?? []) as $r) {
            $reviews[] = $this->mapReview($r, $fallbackBusinessName);
        }

        $totalReviews = (int) ($data['data']['businessRating']['votes'] ?? $data['data']['params']['count'] ?? 0);

        return [
            'reviews' => $reviews,
            'rating' => (float) ($data['data']['businessRating']['score'] ?? 0),
            'total' => $totalReviews,
            'totalPages' => (int) ceil($totalReviews / 50),
        ];
    }

    /**
     * Преобразование одиночного отзыва.
     */
    public function mapReview(array $r, ?string $fallbackBusinessName = null): array
    {
        $publishedAt = null;
        if (! empty($r['updatedTime'])) {
            $publishedAt = date('Y-m-d H:i:s', strtotime($r['updatedTime']));
        }

        return [
            'yandex_review_id' => (string) ($r['reviewId'] ?? $r['id'] ?? uniqid('r_', true)),
            'author_name' => $r['author']['name'] ?? 'Аноним',
            'author_phone' => null,
            'branch_name' => $r['branchName'] ?? $r['org']['name'] ?? $r['business']['name'] ?? $fallbackBusinessName,
            'rating' => (int) ($r['rating'] ?? 0),
            'text' => $r['text'] ?? null,
            'published_at' => $publishedAt,
        ];
    }
}
