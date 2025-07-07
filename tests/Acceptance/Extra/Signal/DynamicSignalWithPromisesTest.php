<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Signal\DynamicSignalWithPromises;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

class DynamicSignalWithPromisesTest extends TestCase
{
    #[Test]
    public function steps(
        #[Stub('Extra_Signal_DynamicSignalWithPromises')] WorkflowStubInterface $stub,
    ): void {
        # Send signals to the workflow to trigger steps
        $stub->signal('begin', 'foo');
        $stub->signal('next1', 'bar');

        # Assert that the workflow has processed the signals and updated the value
        $this->assertSame(2, $stub->query('value')->getValue(0, 'int'));

        # Send another signal to continue the workflow
        $stub->signal('next2', 'baz');

        # Assert that the workflow has processed the final signal and returned the expected value
        $this->assertSame(3, $stub->query('value')->getValue(0, 'int'));

        # Assert that the workflow has completed and returned the final result
        $this->assertSame(3, $stub->getResult());
    }
}

#[Workflow\WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: 'Extra_Signal_DynamicSignalWithPromises')]
    public function handler()
    {
        $value = 0;
        Workflow::registerQuery('value', static function () use (&$value) {
            return $value;
        });

        yield $this->promiseSignal('begin');
        $value++;

        yield $this->promiseSignal('next1');
        $value++;

        yield $this->promiseSignal('next2');
        $value++;

        return $value;
    }

    private function promiseSignal(string $name): PromiseInterface
    {
        $signal = new Deferred();
        Workflow::registerSignal($name, static function (mixed $value) use ($signal): void {
            $signal->resolve($value);
        });

        return $signal->promise();
    }
}
