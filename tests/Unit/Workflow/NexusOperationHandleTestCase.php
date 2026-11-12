<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use React\Promise\Deferred;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\NexusOperationHandle;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusOperationHandle::class)]
final class NexusOperationHandleTestCase extends AbstractUnit
{
    public function testGetResultReturnsTheWrappedPromise(): void
    {
        $deferred = new Deferred();
        $handle = new NexusOperationHandle(
            operationToken: null,
            rawResult: $deferred->promise(),
        );

        $received = null;
        $handle->getResult()->then(
            function ($v) use (&$received): void {
                $received = $v;
            },
        );

        $deferred->resolve('hello');
        self::assertSame('hello', $received);
    }

    public function testGetResultIsIdempotent(): void
    {
        $handle = new NexusOperationHandle(
            operationToken: null,
            rawResult: (new Deferred())->promise(),
        );

        self::assertSame($handle->getResult(), $handle->getResult());
    }

    public function testTokenAvailableBeforeResultResolves(): void
    {
        $deferred = new Deferred();
        $handle = new NexusOperationHandle(
            operationToken: 'observed-while-pending',
            rawResult: $deferred->promise(),
        );

        self::assertSame('observed-while-pending', $handle->getOperationToken());

        $resolved = false;
        $handle->getResult()->then(static function () use (&$resolved): void {
            $resolved = true;
        });
        self::assertFalse($resolved);
    }

    public function testGetOperationTokenReturnsNullForSyncOperation(): void
    {
        $handle = new NexusOperationHandle(
            operationToken: null,
            rawResult: (new Deferred())->promise(),
        );

        self::assertNull($handle->getOperationToken());
    }

    public function testGetOperationTokenReturnsTokenForAsyncOperation(): void
    {
        $handle = new NexusOperationHandle(
            operationToken: 'op-token-xyz',
            rawResult: (new Deferred())->promise(),
        );

        self::assertSame('op-token-xyz', $handle->getOperationToken());
    }
}
