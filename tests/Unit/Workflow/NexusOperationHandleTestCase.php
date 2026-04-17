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
        $handle = new NexusOperationHandle($deferred->promise());

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
        $deferred = new Deferred();
        $handle = new NexusOperationHandle($deferred->promise());

        // Multiple calls must return the same promise — callers may attach
        // handlers at different points in the workflow without spawning
        // duplicate operations.
        self::assertSame($handle->getResult(), $handle->getResult());
    }

    public function testGetOperationTokenCurrentlyAlwaysNull(): void
    {
        // The current wire (ExecuteNexusOperation atomic start+wait) does
        // not surface the token. This test documents that contract — once
        // the wire evolves, adjust the expectation accordingly.
        $handle = new NexusOperationHandle((new Deferred())->promise());

        self::assertNull($handle->getOperationToken());
    }
}
