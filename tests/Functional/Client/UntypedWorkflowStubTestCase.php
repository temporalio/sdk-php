<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TerminatedFailure;
use Temporal\Exception\IllegalStateException;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Workflow\WorkflowExecutionStatus;

/**
 * @group client
 * @group functional
 */
class UntypedWorkflowStubTestCase extends AbstractClient
{
    public function testUntypedStartAndWaitResult()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $this->assertSame('HELLO WORLD', $simple->getResult());
    }

    public function testUntypedStartWithWrongData()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleWorkflow');
        $client->start($simple, ['hello world']);

        $this->expectException(WorkflowFailedException::class);
        $simple->getResult();
    }

    public function testUntypedStartViaClient()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleWorkflow');
        $r = $client->start($simple, 'test');

        $this->assertNotEmpty($r->getExecution()->getID());
        $this->assertNotEmpty($r->getExecution()->getRunID());

        $this->assertSame('TEST', $r->getResult());
    }

    public function testStartWithSameID()
    {
        $this->markTestSkipped('Currently not supported "getStartRequestId" on test server');
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple2 = $client->newUntypedWorkflowStub(
            'SimpleWorkflow',
            WorkflowOptions::new()
                ->withWorkflowId($e->getExecution()->getID())
        );

        $this->expectException(WorkflowExecutionAlreadyStartedException::class);
        $client->start($simple2, 'hello world');
    }

    public function testSignalWorkflowAndGetResult()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $e = $client->start($simple);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->signal('add', -1);

        $this->assertSame(-1, $simple->getResult());
    }

    public function testSignalWithStart()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $e = $client->startWithSignal($simple, 'add', [-1]);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->signal('add', -1);

        $this->assertSame(-2, $simple->getResult());
    }

    public function testSignalWithStartAlreadyStarted()
    {
        $this->markTestSkipped('Currently not supported "getStartRequestId" on test server');

        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $e = $client->startWithSignal($simple, 'add', [-1]);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->signal('add', -1);

        $this->assertSame(-2, $simple->getResult());

        $simple2 = $client->newUntypedWorkflowStub(
            'SimpleWorkflow',
            WorkflowOptions::new()
                ->withWorkflowId($e->getExecution()->getID())
        );

        $this->expectException(WorkflowExecutionAlreadyStartedException::class);
        $e = $client->startWithSignal($simple2, 'add', [-1]);
    }

    public function testSignalNotStarted()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $this->expectException(IllegalStateException::class);
        $simple->signal('add', -1);
    }

    public function testQueryNotStarted()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $this->expectException(IllegalStateException::class);
        $simple->query('get');
    }

    public function testQueryWorkflow()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('QueryWorkflow');

        $e = $client->start($simple);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->signal('add', 88);

        $this->assertSame(88, $simple->query('get')->getValue(0));
        $this->assertSame(88, $simple->getResult());
    }

    public function testStartAsNewEventTrailing()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('ContinuableWorkflow');

        $e = $client->start($simple, 1);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $this->assertSame('OK6', $simple->getResult());
    }

    public function testCancelled()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $e = $client->start($simple, -1);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->cancel();
        try {
            $simple->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(CanceledFailure::class, $e->getPrevious());
        }
    }

    public function testTerminated()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $e = $client->start($simple, -1);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->terminate('user triggered');
        try {
            $simple->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(TerminatedFailure::class, $e->getPrevious());
        }
    }

    public function testDescribe(): void
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('SimpleSignalledWorkflowWithSleep');

        $run = $client->start($simple, -1);
        $startAt = \microtime(true);

        do {
            $stubDescription = $simple->describe();
            // 5 seconds limit
            if (\microtime(true) - $startAt > 5) {
                throw new InvalidArgumentException('Workflow execution not started');
            }
            // wait for workflow was started on a worker
        } while ($stubDescription->info->historyLength < 5);

        $runDescription = $run->describe();

        self::assertEquals($stubDescription, $runDescription);
        self::assertSame(WorkflowExecutionStatus::Running, $runDescription->info->status);

        $simple->terminate('user triggered');
        try {
            // Wait for termination
            $simple->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(TerminatedFailure::class, $e->getPrevious());
        }

        $stubDescription = $simple->describe();
        $runDescription = $run->describe();

        // After termination
        self::assertEquals($stubDescription, $runDescription);
        self::assertSame(WorkflowExecutionStatus::Terminated, $runDescription->info->status);
        self::assertSame(WorkflowExecutionStatus::Terminated, $stubDescription->info->status);
    }

    public function testSignalRunningWorkflowWithInheritedSignal()
    {
        $client = $this->createClient();

        $workflow = $client->newUntypedWorkflowStub('SignalledWorkflowWithInheritance');
        $workflowRun = $client->start($workflow, 1);
        $workflowId = $workflowRun->getExecution()->getID();
        $workflowRunId = $workflowRun->getExecution()->getRunID();

        $signaller = $client->newUntypedRunningWorkflowStub($workflowId, $workflowRunId);
        $signaller->signal('addValue', 'test1');

        $result = $workflowRun->getResult(timeout: 10);
        $this->assertEquals(['test1'], $result);
    }
}
