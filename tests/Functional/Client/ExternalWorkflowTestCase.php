<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Testing\WithoutTimeSkipping;
use Temporal\Tests\Workflow\LoopKillerWorkflow;
use Temporal\Tests\Workflow\LoopSignallingWorkflow;
use Temporal\Tests\Workflow\LoopWorkflow;

class ExternalWorkflowTestCase extends ClientTestCase
{
    use WithoutTimeSkipping;

    public function testSignalWorkflowExecution()
    {
        $client = $this->createClient();

        // start pending workflow
        $loop = $client->newWorkflowStub(LoopWorkflow::class);
        $loopRun = $client->start($loop, 1);

        // signal via internal workflow
        $signaller = $client->newWorkflowStub(LoopSignallingWorkflow::class);
        $signaller->run($loopRun->getExecution());

        $result = $loopRun->getResult();
        $this->assertEquals(['LOOP'], $result);
    }

    public function testSignalWorkflowExecutionByIDOnly()
    {
        $client = $this->createClient();

        // start pending workflow
        $loop = $client->newWorkflowStub(LoopWorkflow::class);
        $loopRun = $client->start($loop, 1);

        // signal via internal workflow
        $signaller = $client->newWorkflowStub(LoopSignallingWorkflow::class);
        $signaller->run($loopRun->getExecution(), true);

        $result = $loopRun->getResult();
        $this->assertEquals(['LOOP'], $result);
    }

    public function testRequestCancelExternalWorkflow()
    {
        $client = $this->createClient();

        // start pending workflow
        $loop = $client->newWorkflowStub(LoopWorkflow::class);
        $loopRun = $client->start($loop, 1);

        // signal via internal workflow
        $signaller = $client->newWorkflowStub(LoopKillerWorkflow::class);
        $signaller->run($loopRun->getExecution());

        try {
            $result = $loopRun->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(CanceledFailure::class, $e->getPrevious());
        }
    }

    public function testRequestCancelExternalWorkflowWithoutRunId()
    {
        $client = $this->createClient();

        // start pending workflow
        $loop = $client->newWorkflowStub(LoopWorkflow::class);
        $loopRun = $client->start($loop, 1);

        // signal via internal workflow
        $signaller = $client->newWorkflowStub(LoopKillerWorkflow::class);
        $signaller->run($loopRun->getExecution(), true);

        try {
            $result = $loopRun->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(CanceledFailure::class, $e->getPrevious());
        }
    }
}
