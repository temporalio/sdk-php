<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\FallbackHandlers;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
class FallbackHandlersTest extends TestCase
{
    #[Test]
    public function fallbackSignal(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::registerSignalFallback() */
        $stub->signal('register_signals');

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
    }

    #[Test]
    public function fallbackSignalDeferred(
        #[Stub('Extra_Workflow_FallbackHandlers')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('foo', 'bar', 'baz');
        $stub->signal('foo', 42);
        $stub->signal('baz', ['foo' => 'bar']);

        /** @see TestWorkflow::ping() */
        $stub->update('ping');
        /** @see TestWorkflow::registerSignalFallback() */
        $stub->signal('register_signals');

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

        /** @see TestWorkflow::ping() */
        $stub->update('ping');
        /** @see TestWorkflow::registerSignalFallback() */
        $stub->signal('register_signals');

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
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $signals = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_FallbackHandlers")]
    public function handle()
    {
        yield Workflow::await(
            fn(): bool => $this->exit,
        );
        return [
            'signals' => $this->signals,
        ];
    }

    #[Workflow\SignalMethod('register_signals')]
    public function registerSignalFallback()
    {
        Workflow::registerFallbackSignal(function (string $name, ValuesInterface $values): void {
            $this->signals[] = [$name, $values->getValues()];
        });
    }

    #[Workflow\UpdateMethod]
    public function ping()
    {
        return 'pong';
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
