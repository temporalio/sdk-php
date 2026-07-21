<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\HeaderCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderCollection::class)]
final class HeaderCollectionTest extends TestCase
{
    public function testGetIsCaseInsensitive(): void
    {
        $headers = new HeaderCollection(['Content-Type' => 'text/plain']);

        self::assertSame('text/plain', $headers->get('content-type'));
        self::assertSame('text/plain', $headers->get('Content-Type'));
        self::assertSame('text/plain', $headers->get('CONTENT-TYPE'));
    }

    public function testGetReturnsNullForUnknownHeader(): void
    {
        $headers = new HeaderCollection(['x-known' => 'v']);

        self::assertNull($headers->get('x-unknown'));
    }

    public function testHasIsCaseInsensitive(): void
    {
        $headers = new HeaderCollection(['X-Trace-Id' => 'abc']);

        self::assertTrue($headers->has('x-trace-id'));
        self::assertTrue($headers->has('X-Trace-Id'));
        self::assertTrue($headers->has('X-TRACE-ID'));
        self::assertFalse($headers->has('x-other'));
    }

    public function testAllReturnsNormalizedMap(): void
    {
        $headers = new HeaderCollection(['UPPER' => 'A', 'lower' => 'b']);

        self::assertSame(['upper' => 'A', 'lower' => 'b'], $headers->all());
    }

    public function testEmptyCollection(): void
    {
        $headers = new HeaderCollection();

        self::assertSame([], $headers->all());
        self::assertFalse($headers->has('anything'));
        self::assertNull($headers->get('anything'));
    }
}
