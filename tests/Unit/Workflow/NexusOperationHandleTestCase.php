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
