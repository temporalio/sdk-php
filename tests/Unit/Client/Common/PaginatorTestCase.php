<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Common;

use Generator;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Common\Paginator;

final class PaginatorTestCase extends TestCase
{
    public function testNextPage(): void
    {
        $paginator = Paginator::createFromGenerator($this->createGenerator(), null);

        self::assertCount(3, $paginator->getPageItems());
        self::assertCount(3, $paginator->getNextPage()->getPageItems());
        self::assertNotNull($paginator->getNextPage());
        // Next page is cached
        self::assertSame($paginator->getNextPage(), $paginator->getNextPage());
        self::assertNotNull($paginator->getNextPage()->getNextPage());
        self::assertCount(1, $paginator->getNextPage()->getNextPage()->getPageItems());
        self::assertNull($paginator->getNextPage()->getNextPage()->getNextPage());
    }

    public function testPageNumber(): void
    {
        $paginator = Paginator::createFromGenerator($this->createGenerator(), null);

        self::assertSame(1, $paginator->getPageNumber());
        self::assertSame(2, $paginator->getNextPage()->getPageNumber());
        self::assertSame(3, $paginator->getNextPage()->getNextPage()->getPageNumber());
    }

    public function testIterator(): void
    {
        $paginator = Paginator::createFromGenerator($this->createGenerator(), null);

        $array = \iterator_to_array($paginator);
        self::assertCount(7, $array);
    }

    /**
     * @return Generator<array-key, list<int>>
     */
    private function createGenerator(): Generator
    {
        yield [1, 2, 3];
        yield [4, 5, 6];
        yield [7];
    }
}
