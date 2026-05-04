<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Interceptor\Header;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Workflow\NexusOperationStub;
use Temporal\Workflow\NexusOperationOptions;

/**
 * @group unit
 * @group nexus
 */
final class NexusOperationStubTestCase extends TestCase
{
    public function testStartRejectsEmptyEndpoint(): void
    {
        $stub = $this->makeStub(NexusOperationOptions::new());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Nexus stub for this operation has no endpoint set. "
            . "Call NexusOperationOptions::withEndpoint('your-endpoint') "
            . "before passing options to newNexusServiceStub() or newUntypedNexusOperationStub().",
        );

        $stub->start('someOp');
    }

    public function testStartRejectsEmptyEndpointMentionsServiceWhenKnown(): void
    {
        $stub = $this->makeStub(
            NexusOperationOptions::new()->withService('PaymentService'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Nexus stub for service 'PaymentService' has no endpoint set. "
            . "Call NexusOperationOptions::withEndpoint('your-endpoint') "
            . "before passing options to newNexusServiceStub() or newUntypedNexusOperationStub().",
        );

        $stub->start('someOp');
    }

    public function testStartRejectsEmptyService(): void
    {
        $stub = $this->makeStub(
            NexusOperationOptions::new()->withEndpoint('ep'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus service is empty');

        $stub->start('someOp');
    }

    public function testStartRejectsEmptyOperationName(): void
    {
        $stub = $this->makeStub(
            NexusOperationOptions::new()
                ->withEndpoint('ep')
                ->withService('svc'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus operation name must be a non-empty string');

        $stub->start('');
    }

    public function testNormalizeFailureWrapsCanceledFailure(): void
    {
        $deferred = new Deferred();
        $normalized = $this->invokeNormalizeFailure($deferred->promise());

        $captured = null;
        $normalized->then(
            null,
            static function (\Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $deferred->reject(new CanceledFailure('cancelled by caller'));

        self::assertInstanceOf(NexusOperationFailure::class, $captured);
        self::assertSame('nexus operation cancelled', $captured->getOriginalMessage());
        self::assertSame('ep', $captured->getEndpoint());
        self::assertSame('svc', $captured->getService());
        self::assertSame('place-order', $captured->getOperation());
        self::assertSame('', $captured->getOperationToken());
        self::assertSame(0, $captured->getScheduledEventId());
        self::assertInstanceOf(CanceledFailure::class, $captured->getPrevious());
    }

    public function testNormalizeFailureWrapsGenericThrowable(): void
    {
        $deferred = new Deferred();
        $normalized = $this->invokeNormalizeFailure($deferred->promise());

        $captured = null;
        $normalized->then(
            null,
            static function (\Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $original = new ApplicationFailure('boom', 'BoomType', false);
        $deferred->reject($original);

        self::assertInstanceOf(NexusOperationFailure::class, $captured);
        self::assertSame('nexus operation completed unsuccessfully', $captured->getOriginalMessage());
        self::assertSame($original, $captured->getPrevious());
    }

    public function testNormalizeFailurePassesThroughExistingNexusOperationFailure(): void
    {
        $deferred = new Deferred();
        $normalized = $this->invokeNormalizeFailure($deferred->promise());

        $captured = null;
        $normalized->then(
            null,
            static function (\Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        // Simulates a server-originated failure that already arrived wrapped
        // — must not be re-wrapped (would lose the real scheduledEventId /
        // operationToken from the wire).
        $original = new NexusOperationFailure(
            message: 'handler returned error',
            scheduledEventId: 42,
            endpoint: 'wire-ep',
            service: 'wire-svc',
            operation: 'wire-op',
            operationToken: 'tok-123',
        );
        $deferred->reject($original);

        self::assertSame($original, $captured);
        self::assertSame(42, $captured->getScheduledEventId());
        self::assertSame('tok-123', $captured->getOperationToken());
        self::assertSame('wire-ep', $captured->getEndpoint());
    }

    public function testNormalizeFailurePassesThroughResolvedValue(): void
    {
        $deferred = new Deferred();
        $normalized = $this->invokeNormalizeFailure($deferred->promise());

        $received = null;
        $normalized->then(
            static function ($value) use (&$received): void {
                $received = $value;
            },
        );

        // Verifies the success path is not broken by the new rejection-only
        // handler — values flow through unchanged.
        $deferred->resolve('payload');

        self::assertSame('payload', $received);
    }

    private function makeStub(NexusOperationOptions $options): NexusOperationStub
    {
        /** @var MarshallerInterface<array> $marshaller */
        $marshaller = $this->createStub(MarshallerInterface::class);
        return new NexusOperationStub(
            $marshaller,
            $options,
            Header::empty(),
        );
    }

    /**
     * Reach into the private normalizeFailure() — the class is final so we
     * cannot subclass to override request(), and start() requires a real
     * Workflow context. Reflection keeps the unit boundary at the function
     * under test without dragging in the workflow runtime.
     */
    private function invokeNormalizeFailure(PromiseInterface $promise): PromiseInterface
    {
        $stub = $this->makeStub(
            NexusOperationOptions::new()->withEndpoint('ep')->withService('svc'),
        );

        $method = new \ReflectionMethod(NexusOperationStub::class, 'normalizeFailure');

        return $method->invoke($stub, $promise, 'ep', 'svc', 'place-order');
    }
}
