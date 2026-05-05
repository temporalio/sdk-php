<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\NexusOperationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusOperationContext::class)]
#[UsesClass(InvalidArgumentException::class)]
final class NexusOperationContextTest extends TestCase
{
    public function testConstructStoresFields(): void
    {
        $ctx = new NexusOperationContext('ns', 'tq');

        self::assertSame('ns', $ctx->namespace);
        self::assertSame('tq', $ctx->taskQueue);
    }

    public function testRejectsEmptyNamespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('namespace must not be empty');

        new NexusOperationContext('', 'tq');
    }

    public function testRejectsEmptyTaskQueue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('taskQueue must not be empty');

        new NexusOperationContext('ns', '');
    }

    public function testDoesNotExposeWorkflowClient(): void
    {
        $ctx = new NexusOperationContext('ns', 'tq');

        self::assertFalse(
            \property_exists($ctx, 'workflowClient'),
            'public NexusOperationContext must not leak the WorkflowClient',
        );
    }
}
