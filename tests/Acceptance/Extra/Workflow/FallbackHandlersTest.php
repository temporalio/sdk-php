<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\FallbackHandlers;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(pipelineProvider: [WorkerServices::class, 'interceptors'])]
class FallbackHandlersTest extends TestCase
{
    #[Test]
    public function fallbackQuery(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        try {
            $stub->query('foo', 'bar', 'baz');
            self::fail('Query should not be registered');
        } catch (WorkflowQueryException) {
            // Ignore the exception
        }

        /** @see TestWorkflow::registerQueryFallback() */
        $stub->update('register_query_fallback');

        self::assertSame(
            'Got query `foo` with 2 arguments',
            $stub->query('foo', 'bar', 'baz')?->getValues()[0] ?? null,
            'Query should be handled by the fallback handler',
        );

        // Check interceptors working
        self::assertGreaterThanOrEqual(1, \count($logger->findByMessage('/Intercepted query: foo/')));
    }

    #[Test]
    public function fallbackSignal(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        /** @see TestWorkflow::registerSignalFallback() */
        $stub->update('register_signals_fallback');

        $stub->signal('foo', 'bar', 'baz');
        $stub->signal('foo', 42);
        $stub->signal('baz', ['foo' => 'bar']);

        /** @see TestWorkflow::exit() */
        $stub->signal('exit');
        // Should be completed after the previous operation
        $result = $stub->getResult('array');

        $this->assertSame([
            ['foo', ['bar', 'baz']],
            ['foo', [42]],
            ['baz', [['foo' => 'bar']]],
        ], $result['signals']);

        // Check interceptors working
        self::assertCount(2, $logger->findByMessage('/Intercepted signal: foo/'));
        self::assertCount(1, $logger->findByMessage('/Intercepted signal: baz/'));
    }

    #[Test]
    public function fallbackSignalDeferred(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('foo', 'bar', 'baz');
        $stub->signal('foo', 42);
        $stub->signal('baz', ['foo' => 'bar']);

        /** @see TestWorkflow::registerSignalFallback() */
        $stub->update('register_signals_fallback');

        /** @see TestWorkflow::exit() */
        $stub->signal('exit');
        // Should be completed after the previous operation
        $result = $stub->getResult('array');

        $this->assertSame([
            ['foo', ['bar', 'baz']],
            ['foo', [42]],
            ['baz', [['foo' => 'bar']]],
        ], $result['signals']);
    }

    #[Test]
    public function fallbackSignalOrder(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('foo', 1);
        $stub->signal('foo', 2);
        $stub->signal('baz', 3);
        $stub->signal('foo', 4);
        $stub->signal('baz', 5);

        /** @see TestWorkflow::registerSignalFallback() */
        $stub->update('register_signals_fallback');

        /** @see TestWorkflow::exit() */
        $stub->signal('exit');
        // Should be completed after the previous operation
        $result = $stub->getResult('array');

        $this->assertSame([
            ['foo', [1]],
            ['foo', [2]],
            ['baz', [3]],
            ['foo', [4]],
            ['baz', [5]],
        ], $result['signals']);
    }

    #[Test]
    public function fallbackUpdate(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        /** @see TestWorkflow::registerUpdateFallback() */
        $stub->update('register_updates_fallback', false);

        $stub->update('foo', 'bar', 'baz');
        $stub->update('foo', 42);
        $stub->update('baz', ['foo' => 'bar']);

        /** @see TestWorkflow::exit() */
        $stub->signal('exit');
        // Should be completed after the previous operation
        $result = $stub->getResult('array');

        $this->assertSame([
            ['foo', ['bar', 'baz']],
            ['foo', [42]],
            ['baz', [['foo' => 'bar']]],
        ], $result['updates']);

        // Check interceptors working
        self::assertCount(2, $logger->findByMessage('/Intercepted update: foo/'));
        self::assertCount(1, $logger->findByMessage('/Intercepted update: baz/'));
        self::assertCount(0, $logger->findByMessage('/Intercepted update validator: foo/'));
        self::assertCount(0, $logger->findByMessage('/Intercepted update validator: foo/'));
    }

    #[Test]
    public function fallbackUpdateValidationFail(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        /** @see TestWorkflow::registerUpdateFallback() */
        $stub->update('register_updates_fallback', true);

        // Check that fallback validator was not called for predefined Update handler
        $stub->update('register_updates_fallback', true);

        // Validation passed
        $stub->update('foo', 'bar', 'baz');

        // Check interceptors working
        self::assertCount(1, $logger->findByMessage('/Intercepted update: foo/'));
        self::assertCount(1, $logger->findByMessage('/Intercepted update validator: foo/'));

        // Validation failed
        $this->expectException(WorkflowUpdateException::class);
        $stub->update('fail', 42);
    }
}


class WorkerServices
{
    public static function interceptors(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new WorkflowInboundInterceptor(),
        ]);
    }
}

final class WorkflowInboundInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    public function handleSignal(SignalInput $input, callable $next): void
    {
        $input->isReplaying or Workflow::getLogger()->info('Intercepted signal: ' . $input->signalName);
        $next($input);
    }

    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        Workflow::getLogger()->info('Intercepted query: ' . $input->queryName);
        return $next($input);
    }

    public function handleUpdate(UpdateInput $input, callable $next): mixed
    {
        $input->isReplaying or Workflow::getLogger()->info('Intercepted update: ' . $input->updateName);
        return $next($input);
    }

    /**
     * Default implementation of the `validateUpdate` method.
     *
     * @see WorkflowInboundCallsInterceptor::validateUpdate()
     */
    public function validateUpdate(UpdateInput $input, callable $next): void
    {
        Workflow::getLogger()->info('Intercepted update validator: ' . $input->updateName);
        $next($input);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    private array $signals = [];
    private array $updates = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_FallbackHandlers")]
    public function handle()
    {
        yield Workflow::await(
            fn(): bool => $this->exit,
        );
        return [
            'signals' => $this->signals,
            'updates' => $this->updates,
        ];
    }

    #[Workflow\UpdateMethod('register_query_fallback')]
    public function registerQueryFallback(): void
    {
        Workflow::registerDynamicQuery(static fn(string $name, ValuesInterface $values): string => \sprintf(
            'Got query `%s` with %d arguments',
            $name,
            $values->count(),
        ));
    }

    #[Workflow\UpdateMethod('register_signals_fallback')]
    public function registerSignalFallback(): void
    {
        Workflow::registerDynamicSignal(function (string $name, ValuesInterface $values): void {
            $this->signals[] = [$name, $values->getValues()];
        });
    }

    #[Workflow\UpdateMethod('register_updates_fallback')]
    public function registerUpdateFallback(bool $validator): void
    {
        Workflow::registerDynamicUpdate(
            fn(string $name, ValuesInterface $values): array => $this->updates[] = [$name, $values->getValues()],
            $validator
                ? static fn(string $name, ValuesInterface $values): bool => \in_array(
                    $name,
                    ['fail', 'register_updates_fallback'],
                    true,
                ) and throw new \Exception('Failed with ' . $values->count() . ' arguments')
                : null,
        );
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
