<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Workflow;

use Temporal\Client\Exception\CancellationException;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Declaration\WorkflowInstance;
use Temporal\Client\Internal\Workflow\Input;
use Temporal\Client\Internal\Workflow\Process\Process;
use Temporal\Client\Workflow\WorkflowInfo;
use Temporal\Tests\Client\Workflow\CancellationScopeTestCase\WorkflowMock;

class CancellationScopeTestCase extends WorkflowTestCase
{
    /**
     * @return void
     */
    public function testFirst(): void
    {
        $this->createProcess(WorkflowMock::class, 'first');

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
        ;

        $this->queue->shift()
            ->assertName('CompleteWorkflow')
            ->assertParamsKeySame('result', [0xDEAD_BEEF])
        ;
    }

    private function createProcess(string $class, string $fun, WorkflowInfo $info = null, array $args = []): Process
    {
        $input = new Input($info, $args);

        return new Process($input, $this->services, $this->createInstance($class, $fun));
    }

    /**
     * @param string $class
     * @param string $function
     * @return WorkflowInstance
     * @throws \ReflectionException
     */
    private function createInstance(string $class, string $function): WorkflowInstance
    {
        $reflectionClass = new \ReflectionClass($class);

        $reflectionFunction = $reflectionClass->getMethod($function);

        $prototype = new WorkflowPrototype($reflectionFunction->getName(), $reflectionFunction, $reflectionClass);

        return new WorkflowInstance($prototype, new $class());
    }

    /**
     * @return void
     */
    public function testSecondWithFirstPromise(): void
    {
        $this->createProcess(WorkflowMock::class, 'second');

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0)
        ;

        $first = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'a')
        ;

        $second = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'b')
        ;

        $this->successResponseAndNext($first, $first->getId());

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0)
        ;

        $this->queue->shift()
            ->assertName('Cancel')
            ->assertParamsKeySame('ids', [$second->getId()])
        ;

        $this->queue->shift()
            ->assertName('CompleteWorkflow')
            ->assertParamsKeySame('result', [$first->getId()])
        ;
    }

    /**
     * @return void
     */
    public function testSecondWithSecondPromise(): void
    {
        $this->createProcess(WorkflowMock::class, 'second');

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0)
        ;

        $first = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'a')
        ;

        $second = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'b')
        ;

        $this->successResponseAndNext($second, $second->getId());

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0)
        ;

        $this->queue->shift()
            ->assertName('Cancel')
            ->assertParamsKeySame('ids', [$first->getId()])
        ;

        $this->queue->shift()
            ->assertName('CompleteWorkflow')
            ->assertParamsKeySame('result', [$second->getId()])
        ;
    }

    public function testNested(): void
    {
        $this->createProcess(WorkflowMock::class, 'simpleNested');

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySame('result', [42])
        ;
    }

    public function testMemoizePromise(): void
    {
        $this->createProcess(WorkflowMock::class, 'memoizedPromise');

        $result = $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->getParam('result.0')
        ;

        $this->assertInstanceOf(CancellationException::class, $result);
    }

    public function testRace(): void
    {
        $this->createProcess(WorkflowMock::class, 'race');

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0)
        ;

        $first = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'first')
        ;

        $second = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'second')
        ;

        $this->successResponseAndNext($first, 'RESULT');

        $this->queue->assertCount(0);

        $this->successResponseAndNext($second);

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySame('result', ['RESULT'])
        ;
    }

    public function testNestedCancelled(): void
    {
        $this->createProcess(WorkflowMock::class, 'simpleNestingScopeCancelled');

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySame('result', [42])
        ;
    }
}
