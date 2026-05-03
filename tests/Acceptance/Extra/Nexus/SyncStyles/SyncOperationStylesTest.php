<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\SyncStyles;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\SynchronousOperationFunctionInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: all three ways to build a synchronous Nexus operation handler
 * produce identical wire-level behaviour.
 *
 *   1. `new SynchronousOperationHandler(callable)`              — BC constructor form.
 *   2. `SynchronousOperationHandler::fromCallable(callable)`    — explicit callable factory.
 *   3. `SynchronousOperationHandler::fromFunction($functor)`    — explicit functor factory,
 *      backed by SynchronousOperationFunctionInterface.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class SyncOperationStylesTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function callableViaConstructor(
        State $state,
        #[Stub('Extra_Nexus_SyncStyles_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'viaCallableCtor', 'alpha');

        self::assertSame(200, $code, "Response: {$resp}");
        self::assertStringContainsString('ALPHA!', $resp);
    }

    #[Test]
    public function callableViaFromCallableFactory(
        State $state,
        #[Stub('Extra_Nexus_SyncStyles_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'viaFromCallable', 'beta');

        self::assertSame(200, $code, "Response: {$resp}");
        self::assertStringContainsString('BETA!', $resp);
    }

    #[Test]
    public function functorViaConstructor(
        State $state,
        #[Stub('Extra_Nexus_SyncStyles_Bootstrap3')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'viaFunctorCtor', 'gamma');

        self::assertSame(200, $code, "Response: {$resp}");
        self::assertStringContainsString('GAMMA!', $resp);
    }

    #[Test]
    public function functorViaFromFunctionFactory(
        State $state,
        #[Stub('Extra_Nexus_SyncStyles_Bootstrap4')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'viaFromFunction', 'delta');

        self::assertSame(200, $code, "Response: {$resp}");
        self::assertStringContainsString('DELTA!', $resp);
    }

    /**
     * @return array{int, string}
     */
    private function invoke(State $state, string $operation, string $input): array
    {
        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\SyncStyles',
            'nexus-sync-styles',
        );

        return $helper->postOperation($endpointId, 'ShoutService', $operation, $input);
    }
}

// ── Functor fixture ──────────────────────────────────────────────────

/**
 * @implements SynchronousOperationFunctionInterface<string, string>
 */
final class ShoutFunctor implements SynchronousOperationFunctionInterface
{
    public function __invoke(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $input,
    ): mixed {
        return \strtoupper((string) $input) . '!';
    }
}

// ── Nexus service ────────────────────────────────────────────────────

#[Service(name: 'ShoutService')]
interface ShoutServiceInterface
{
    #[Operation]
    public function viaCallableCtor(string $input): string;

    #[Operation]
    public function viaFromCallable(string $input): string;

    #[Operation]
    public function viaFunctorCtor(string $input): string;

    #[Operation]
    public function viaFromFunction(string $input): string;
}

#[ServiceImpl(service: ShoutServiceInterface::class)]
class ShoutServiceImpl
{
    /** Style 1: bare callable passed to ctor. */
    #[OperationImpl]
    public function viaCallableCtor(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $d, ?string $in): string
                => \strtoupper((string) $in) . '!',
        );
    }

    /** Style 2: explicit callable factory. */
    #[OperationImpl]
    public function viaFromCallable(): OperationHandlerInterface
    {
        return SynchronousOperationHandler::fromCallable(
            static fn(OperationContext $ctx, OperationStartDetails $d, ?string $in): string
                => \strtoupper((string) $in) . '!',
        );
    }

    /** Style 3: functor passed to ctor (union param accepts the interface). */
    #[OperationImpl]
    public function viaFunctorCtor(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(new ShoutFunctor());
    }

    /** Style 4: explicit functor factory. */
    #[OperationImpl]
    public function viaFromFunction(): OperationHandlerInterface
    {
        return SynchronousOperationHandler::fromFunction(new ShoutFunctor());
    }
}

// ── Bootstrap workflows (one per #[Test] that needs a worker tickle) ──

#[WorkflowInterface]
class SyncStylesBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncStyles_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class SyncStylesBootstrapWorkflow2
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncStyles_Bootstrap2')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class SyncStylesBootstrapWorkflow3
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncStyles_Bootstrap3')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class SyncStylesBootstrapWorkflow4
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncStyles_Bootstrap4')]
    public function run(): string
    {
        return 'ready';
    }
}
