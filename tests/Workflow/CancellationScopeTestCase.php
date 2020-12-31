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
use Temporal\Tests\Client\Testing\TestingRequest;

class CancellationScopeTestCase extends WorkflowTestCase
{
    public function testFirst(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow1');

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0);

        $this->queue->shift()
            ->assertName('CompleteWorkflow')
            ->assertParamsKeySamePayload('result', [0xDEAD_BEEF]);
    }

    public function testSecondWithFirstPromise(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow2');

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0);

        $first = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'a');

        $second = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'b');

        $this->successResponseAndNext($first, $first->getId());

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0);

        $this->queue->shift()
            ->assertName('Cancel')
            ->assertParamsKeySame('ids', [$second->getId()]);

        $this->queue->shift()
            ->assertName('CompleteWorkflow')
            ->assertParamsKeySamePayload('result', [$first->getId()]);
    }

    public function testSecondWithSecondPromise(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow2');

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0);

        $first = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'a');

        $second = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'b');

        $this->successResponseAndNext($second, $second->getId());

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0);

        $this->queue->shift()
            ->assertName('Cancel')
            ->assertParamsKeySame('ids', [$first->getId()]);

        $this->queue->shift()
            ->assertName('CompleteWorkflow')
            ->assertParamsKeySamePayload('result', [$second->getId()]);
    }

    public function testNested(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow3');

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySamePayload('result', [42]);
    }

    public function testMemoizePromise(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow4');

        $result = $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->getParam('result.0');

        $this->assertInstanceOf(CancellationException::class, $result);
    }

    public function testRace(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow5');

        $this->queue
            ->assertRequestsCount(2)
            ->assertResponsesCount(0);

        $first = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'first');

        $second = $this->queue->shift()
            ->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'second');

        $this->successResponseAndNext($first, 'RESULT');

        $this->queue->assertCount(0);

        $this->successResponseAndNext($second);

        $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySamePayload('result', ['RESULT']);
    }

    public function testNestedCancelled(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow6');

        /** @var TestingRequest $request */
        $request = $this->queue
            ->assertRequestsCount(1)
            ->assertResponsesCount(0)
            ->shift();

        $request->assertName('CompleteWorkflow');

        $result = $request->getParam('result');
        $this->assertIsArray($result);
        $this->assertInstanceOf(CancellationException::class, $result[0]);
    }

    public function testCancellationEvent(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow7');

        $this->queue
            ->assertRequestsCount(1)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySamePayload('result', [true]);
    }

    public function testChainedWorkflow(): void
    {
        $this->createProcess(WorkflowMock::class, 'workflow8');

        $request = $this->queue
            ->assertRequestsCount(1)
            ->shift();

        $request->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'first');

        $this->successResponseAndNext($request, 'FIRST_COMPLETED');

        $request = $this->queue
            ->assertRequestsCount(1)
            ->shift();

        $request->assertName('ExecuteActivity')
            ->assertParamsKeySame('name', 'second')
            ->assertParamsKeySamePayload('arguments', ['Result:FIRST_COMPLETED']);

        $this->successResponseAndNext($request, 0xDEAD_BEEF);

        $this->queue
            ->assertRequestsCount(1)
            ->shift()
                ->assertName('CompleteWorkflow')
                ->assertParamsKeySamePayload('result', [0xDEAD_BEEF]);
    }
}
