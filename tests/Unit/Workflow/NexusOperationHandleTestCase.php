<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use React\Promise\Deferred;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\NexusOperationHandle;

/**
 * @group unit
 * @group nexus
 */
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

        // A non-Values resolution flows through decodePromise unchanged.
        $deferred->resolve('hello');
        self::assertSame('hello', $received);
    }

    public function testGetResultIsIdempotent(): void
    {
        $handle = new NexusOperationHandle(
            operationToken: null,
            rawResult: (new Deferred())->promise(),
        );

        // Multiple calls must return the same promise — callers may attach
        // handlers at different points in the workflow without spawning
        // duplicate operations.
        self::assertSame($handle->getResult(), $handle->getResult());
    }

    public function testSyncHandleHasNullToken(): void
    {
        $handle = new NexusOperationHandle(
            operationToken: null,
            rawResult: (new Deferred())->promise(),
        );

        self::assertNull($handle->operationToken);
        self::assertNull($handle->getOperationToken());
    }

    public function testAsyncHandleExposesTokenViaPropertyAndGetter(): void
    {
        $handle = new NexusOperationHandle(
            operationToken: 'op-token-async-42',
            rawResult: (new Deferred())->promise(),
        );

        // Both the readonly property and the getter must agree — the getter
        // exists for symmetry with the rest of the SDK's stub APIs.
        self::assertSame('op-token-async-42', $handle->operationToken);
        self::assertSame('op-token-async-42', $handle->getOperationToken());
    }

    public function testTokenAvailableBeforeResultResolves(): void
    {
        // The handle is fully populated by the time the caller has it: token
        // is observable while the result-promise is still pending. Workflow
        // code can capture the token and pass it elsewhere before yielding.
        $deferred = new Deferred();
        $handle = new NexusOperationHandle(
            operationToken: 'observed-while-pending',
            rawResult: $deferred->promise(),
        );

        self::assertSame('observed-while-pending', $handle->operationToken);

        $resolved = false;
        $handle->getResult()->then(static function () use (&$resolved): void {
            $resolved = true;
        });
        self::assertFalse($resolved);
    }
}
