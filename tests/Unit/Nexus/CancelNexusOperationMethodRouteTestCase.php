<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Nexus\Handler\MethodCanceller;
use React\Promise\Deferred;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Nexus\NexusInvocationRegistry;
use Temporal\Internal\Transport\Router\CancelNexusOperationMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * Unit tests for the `CancelNexusOperationMethod` router.
 *
 * @group unit
 * @group nexus
 */
#[CoversClass(CancelNexusOperationMethod::class)]
final class CancelNexusOperationMethodRouteTestCase extends AbstractUnit
{
    public function testRouteName(): void
    {
        $route = new CancelNexusOperationMethod(new NexusInvocationRegistry());

        self::assertSame('CancelNexusOperationMethod', $route->getName());
    }

    public function testCancelsRegisteredInvocationWithReason(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller();
        $registry->register(42, $canceller);

        $route = new CancelNexusOperationMethod($registry);
        $request = $this->makeRequest(['invocationId' => 42, 'reason' => 'deadline']);

        $deferred = new Deferred();
        $route->handle($request, [], $deferred);

        $this->assertResolved($deferred);
        self::assertTrue($canceller->isCancelled());
        self::assertSame('deadline', $canceller->getReason());
    }

    public function testUnknownInvocationIdIsNoOp(): void
    {
        $registry = new NexusInvocationRegistry();
        $route = new CancelNexusOperationMethod($registry);
        $request = $this->makeRequest(['invocationId' => 999, 'reason' => 'x']);

        $deferred = new Deferred();
        $route->handle($request, [], $deferred);

        // Still resolves cleanly — a late cancel after handler finished is legitimate.
        $this->assertResolved($deferred);
    }

    public function testZeroInvocationIdSkipsLookup(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller();
        // 0 must NEVER be a valid key — RR assigns monotonic non-zero ids.
        $registry->register(0, $canceller);

        $route = new CancelNexusOperationMethod($registry);
        $request = $this->makeRequest(['invocationId' => 0, 'reason' => 'x']);

        $deferred = new Deferred();
        $route->handle($request, [], $deferred);

        $this->assertResolved($deferred);
        self::assertFalse(
            $canceller->isCancelled(),
            '0 is the sentinel for "no invocation id" and must not touch the registry',
        );
    }

    public function testMissingReasonDefaultsToEmptyString(): void
    {
        $registry = new NexusInvocationRegistry();
        $canceller = new MethodCanceller();
        $registry->register(5, $canceller);

        $route = new CancelNexusOperationMethod($registry);
        $request = $this->makeRequest(['invocationId' => 5]);

        $deferred = new Deferred();
        $route->handle($request, [], $deferred);

        $this->assertResolved($deferred);
        self::assertSame('', $canceller->getReason());
    }

    private function makeRequest(array $options): ServerRequest
    {
        return new ServerRequest(
            name: 'CancelNexusOperationMethod',
            info: new TickInfo(new \DateTimeImmutable()),
            options: $options,
        );
    }

    private function assertResolved(Deferred $deferred): void
    {
        $resolved = false;
        $error = null;
        $deferred->promise()->then(
            function ($value) use (&$resolved): void {
                $resolved = true;
            },
            function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        if ($error !== null) {
            throw $error;
        }
        self::assertTrue($resolved, 'promise should resolve');
    }
}
