<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common;

use Generator;
use Temporal\Common\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    public function testNextPage(): void
    {
        $paginator = Paginator::createFromGenerator($this->createGenerator());

        self::assertCount(3, $paginator->getPageItems());
        self::assertCount(3, $paginator->nextPage()->getPageItems());
        self::assertNotNull($paginator->nextPage());
        // Next page is cached
        self::assertSame($paginator->nextPage(), $paginator->nextPage());
        self::assertNotNull($paginator->nextPage()->nextPage());
        self::assertCount(1, $paginator->nextPage()->nextPage()->getPageItems());
        self::assertNull($paginator->nextPage()->nextPage()->nextPage());
    }

    public function testPageNumber(): void
    {
        $paginator = Paginator::createFromGenerator($this->createGenerator());

        self::assertSame(1, $paginator->getPageNumber());
        self::assertSame(2, $paginator->nextPage()->getPageNumber());
        self::assertSame(3, $paginator->nextPage()->nextPage()->getPageNumber());
    }

    public function testIterator(): void
    {
        $paginator = Paginator::createFromGenerator($this->createGenerator());

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
