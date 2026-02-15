<?php

namespace Tests\Unit;

use App\Services\Yandex\YandexReviewMapper;
use PHPUnit\Framework\TestCase;

class YandexMapsParserTest extends TestCase
{
    public function test_map_review_extracts_branch_name(): void
    {
        $mapper = new YandexReviewMapper();
        
        $reviewData = [
            'reviewId' => '123',
            'author' => ['name' => 'John'],
            'rating' => 5,
            'text' => 'Hello',
            'updatedTime' => '2022-09-12T14:22:00Z',
            'branchName' => 'Филиал 1'
        ];

        $mapped = $mapper->mapReview($reviewData);

        $this->assertEquals('Филиал 1', $mapped['branch_name']);
        $this->assertEquals('John', $mapped['author_name']);
    }

    public function test_map_review_uses_fallback_keys(): void
    {
        $mapper = new YandexReviewMapper();
        
        $reviewData = [
            'reviewId' => '123',
            'author' => ['name' => 'John'],
            'rating' => 5,
            'org' => ['name' => 'Branch from Org']
        ];

        $mapped = $mapper->mapReview($reviewData);
        $this->assertEquals('Branch from Org', $mapped['branch_name']);

        $reviewData2 = [
            'reviewId' => '124',
            'author' => ['name' => 'John'],
            'rating' => 5,
            'business' => ['name' => 'Branch from Business']
        ];

        $mapped2 = $mapper->mapReview($reviewData2);
        $this->assertEquals('Branch from Business', $mapped2['branch_name']);
    }
}
