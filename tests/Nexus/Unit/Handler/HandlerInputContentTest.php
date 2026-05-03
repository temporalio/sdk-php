<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\HandlerInputContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerInputContent::class)]
final class HandlerInputContentTest extends TestCase
{
    public function testConstructorStoresData(): void
    {
        $content = new HandlerInputContent('hello');
        self::assertSame('hello', $content->data);
    }

    public function testConstructorNormalizesHeaders(): void
    {
        $content = new HandlerInputContent('body', ['Content-Type' => 'application/json']);
        self::assertSame(['content-type' => 'application/json'], $content->headers);
    }

    public function testCreateAlsoNormalizes(): void
    {
        $content = new HandlerInputContent('body', ['X-Custom' => 'v']);
        self::assertSame(['x-custom' => 'v'], $content->headers);
    }

    public function testMixedCaseCollisionLastValueWins(): void
    {
        $content = new HandlerInputContent('body', ['X-A' => '1', 'x-a' => '2']);
        self::assertCount(1, $content->headers);
        self::assertSame('2', $content->headers['x-a']);
    }

    public function testEmptyDataAllowed(): void
    {
        $content = new HandlerInputContent('');
        self::assertSame('', $content->data);
    }

    public function testCreateKeepsDataIntact(): void
    {
        $content = new HandlerInputContent('payload', ['a' => 'b']);
        self::assertSame('payload', $content->data);
    }
}
