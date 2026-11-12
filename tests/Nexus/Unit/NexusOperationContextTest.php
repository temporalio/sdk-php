<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\NexusOperationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusOperationContext::class)]
final class NexusOperationContextTest extends TestCase
{
    public function testConstructStoresFields(): void
    {
        $ctx = new NexusOperationContext('ns', 'tq');

        self::assertSame('ns', $ctx->namespace);
        self::assertSame('tq', $ctx->taskQueue);
    }

    public function testDefaultsAreEmpty(): void
    {
        $ctx = new NexusOperationContext();

        self::assertSame('', $ctx->namespace);
        self::assertSame('', $ctx->taskQueue);
    }
}
