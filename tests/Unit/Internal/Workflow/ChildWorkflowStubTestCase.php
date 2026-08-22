<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Header;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Workflow\ChildWorkflowStub;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowContextInterface;

final class ChildWorkflowStubTestCase extends TestCase
{
    public static function preStartCalls(): array
    {
        return [
            'get execution' => ['getExecution', []],
            'get execution async' => ['getExecutionAsync', []],
            'get result' => ['getResult', []],
            'get result async' => ['getResultAsync', []],
            'signal' => ['signal', ['notify']],
            'signal async' => ['signalAsync', ['notify']],
        ];
    }

    #[DataProvider('preStartCalls')]
    public function testOperationsThatRequireAStartedChildFailImmediately(string $method, array $args): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Child workflow has not been started');

        $this->stub()->{$method}(...$args);
    }

    public function testStartFailureRejectsExecutionAndDependentOperations(): void
    {
        $error = new \RuntimeException('child start failed');
        $context = $this->createStub(WorkflowContextInterface::class);
        $context->method('request')->willReturn(Promise::reject($error));
        Workflow::setCurrentContext($context);

        try {
            $stub = $this->stub();
            $this->assertRejectedWith($stub->startAsync(), $error);
            $this->assertRejectedWith($stub->getExecutionAsync(), $error);
            $this->assertRejectedWith($stub->getResultAsync(), $error);
            $this->assertRejectedWith($stub->signalAsync('notify'), $error);
        } finally {
            Workflow::setCurrentContext(null);
        }
    }

    public function testSynchronousStartFailureLeavesAConsistentlyFailedStub(): void
    {
        $error = new \RuntimeException('request setup failed');
        $context = $this->createStub(WorkflowContextInterface::class);
        $context->method('request')->willThrowException($error);
        Workflow::setCurrentContext($context);

        try {
            $stub = $this->stub();

            try {
                $stub->startAsync();
                self::fail('Expected child start setup to fail.');
            } catch (\RuntimeException $actual) {
                self::assertSame($error, $actual);
            }

            $this->assertRejectedWith($stub->getExecutionAsync(), $error);
            $this->assertRejectedWith($stub->getResultAsync(), $error);
            $this->assertRejectedWith($stub->signalAsync('notify'), $error);
        } finally {
            Workflow::setCurrentContext(null);
        }
    }

    public function testExecutionDecodeFailureRejectsAllDependentOperations(): void
    {
        $error = new \RuntimeException('execution payload is invalid');
        $values = $this->createStub(ValuesInterface::class);
        $values->method('getValue')->willThrowException($error);

        $context = $this->createStub(WorkflowContextInterface::class);
        $context->method('request')->willReturnOnConsecutiveCalls(
            Promise::resolve(EncodedValues::empty()),
            Promise::resolve($values),
        );
        Workflow::setCurrentContext($context);

        try {
            $stub = $this->stub();
            $this->assertRejectedWith($stub->startAsync(), $error);
            $this->assertRejectedWith($stub->getExecutionAsync(), $error);
            $this->assertRejectedWith($stub->getResultAsync(), $error);
            $this->assertRejectedWith($stub->signalAsync('notify'), $error);
        } finally {
            Workflow::setCurrentContext(null);
        }
    }

    private function stub(): ChildWorkflowStub
    {
        $marshaller = $this->createStub(MarshallerInterface::class);
        $marshaller->method('marshal')->willReturn([]);

        return new ChildWorkflowStub(
            $marshaller,
            'TestChildWorkflow',
            ChildWorkflowOptions::new(),
            Header::empty(),
        );
    }

    private function assertRejectedWith(PromiseInterface $promise, \Throwable $expected): void
    {
        $actual = null;
        $promise->then(
            static fn() => self::fail('Expected the promise to reject.'),
            static function (\Throwable $error) use (&$actual): void {
                $actual = $error;
            },
        );

        self::assertSame($expected, $actual);
    }
}
