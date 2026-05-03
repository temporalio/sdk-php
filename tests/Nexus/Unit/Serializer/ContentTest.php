<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Serializer;

use Temporal\Nexus\Serializer\Content;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Content::class)]
final class ContentTest extends TestCase
{
    public function testConstruction(): void
    {
        $content = new Content('data', ['content-type' => 'application/json']);
        self::assertSame('data', $content->data);
        self::assertSame('application/json', $content->headers['content-type']);
    }

    public function testCreate(): void
    {
        $content = new Content('data', ['Content-Type' => 'text/plain']);
        self::assertSame('data', $content->data);
        self::assertSame('text/plain', $content->headers['content-type']);
    }
}
