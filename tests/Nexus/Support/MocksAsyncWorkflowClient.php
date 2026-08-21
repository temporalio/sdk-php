<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Support;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Nexus\Fixtures\Service\GreetingService;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;

trait MocksAsyncWorkflowClient
{
    protected function asyncClient(): WorkflowClientInterface
    {
        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('newWorkflowStub')->willReturn($this->createMock(WorkflowStubInterface::class));
        $client->method('newUntypedRunningWorkflowStub')->willReturn($this->createMock(WorkflowStubInterface::class));

        $run = $this->createMock(WorkflowRunInterface::class);
        $run->method('getExecution')->willReturn(new WorkflowExecution(GreetingService::WORKFLOW_ID, 'run-1'));
        $client->method('start')->willReturn($run);

        return $client;
    }
}
