<?php

namespace Tests\Unit;

use App\Services\YandexMapsParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class YandexMapsParserTest extends TestCase
{
    public function test_map_review_extracts_branch_name(): void
    {
        $parser = new YandexMapsParser();
        
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('mapReview');
        $method->setAccessible(true);

        $reviewData = [
            'reviewId' => '123',
            'author' => ['name' => 'John'],
            'rating' => 5,
            'text' => 'Hello',
            'updatedTime' => '2022-09-12T14:22:00Z',
            'branchName' => 'Филиал 1'
        ];

        $mapped = $method->invoke($parser, $reviewData);

        $this->assertEquals('Филиал 1', $mapped['branch_name']);
        $this->assertEquals('John', $mapped['author_name']);
    }

    public function test_map_review_uses_fallback_keys(): void
    {
        $parser = new YandexMapsParser();
        
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('mapReview');
        $method->setAccessible(true);

        $reviewData = [
            'reviewId' => '123',
            'author' => ['name' => 'John'],
            'rating' => 5,
            'org' => ['name' => 'Branch from Org']
        ];

        $mapped = $method->invoke($parser, $reviewData);
        $this->assertEquals('Branch from Org', $mapped['branch_name']);

        $reviewData2 = [
            'reviewId' => '124',
            'author' => ['name' => 'John'],
            'rating' => 5,
            'business' => ['name' => 'Branch from Business']
        ];

        $mapped2 = $method->invoke($parser, $reviewData2);
        $this->assertEquals('Branch from Business', $mapped2['branch_name']);
    }
}
