<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use PHPUnit\Framework\AssertionFailedError;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Tests\Workflow\SignalExceptionsWorkflow;
use Temporal\Tests\Workflow\UpdateExceptionsWorkflow;

/**
 * @group client
 * @group functional
 */
class FailureTestCase extends AbstractClient
{
    public function testSimpleFailurePropagation()
    {
        $client = $this->createClient();
        $ex = $client->newUntypedWorkflowStub('ExceptionalWorkflow');

        $e = $client->start($ex);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        try {
            $this->assertSame('OK', $ex->getResult());
            $this->fail('unreachable');
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('workflow error', $e->getPrevious()->getMessage());
        }
    }

    public function testActivityFailurePropagation()
    {
        $client = $this->createClient();
        $ex = $client->newUntypedWorkflowStub('ExceptionalActivityWorkflow');

        $e = $client->start($ex);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $this->expectException(WorkflowFailedException::class);
        $ex->getResult();
    }

    public function testChildWorkflowFailurePropagation()
    {
        $client = $this->createClient();
        $ex = $client->newUntypedWorkflowStub('ComplexExceptionalWorkflow');

        $e = $client->start($ex);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        try {
            $ex->getResult();
            $this->fail('unreachable');
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ChildWorkflowFailure::class, $e->getPrevious());
            $this->assertStringContainsString('ComplexExceptionalWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            $this->assertStringContainsString('ExceptionalActivityWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('SimpleActivity->fail', $e->getPrevious()->getMessage());
        }
    }

    public function testSignalThatThrowsRetryableException()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(SignalExceptionsWorkflow::class);

        $run = $client->start($wf);

        $wf->failRetryable();

        sleep(1);
        $wf->exit();

        // There is no any exception because the workflow has not failed after the `failRetryable` signal.
        $this->assertTrue(true);
    }

    public function testSignalThatThrowsCustomError()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(SignalExceptionsWorkflow::class);

        $run = $client->start($wf);

        $wf->failWithName('test1');

        try {
            // The next
            sleep(2);
            $wf->exit();
            $this->fail('Signal must fail');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertInstanceOf(WorkflowNotFoundException::class, $e);
            // \dump($e);
        }

        $this->expectException(WorkflowFailedException::class);
        $result = $run->getResult();
        $this->fail(sprintf("Workflow must fail. Got result %s", \print_r($result, true)));
    }

    public function testSignalThatThrowsInvalidArgumentException()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(SignalExceptionsWorkflow::class);

        $run = $client->start($wf);

        $wf->failInvalidArgument('test1');

        $this->expectException(WorkflowFailedException::class);
        $result = $run->getResult();
        $this->fail(sprintf("Workflow must fail. Got result %s", \print_r($result, true)));
    }

    public function testSignalThatThrowsInternalException()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(SignalExceptionsWorkflow::class);

        $run = $client->startWithSignal($wf, 'failActivity', ['foo']);

        try {
            sleep(3);
            $wf->failActivity('foo');
            $this->fail('Signal must fail');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertInstanceOf(WorkflowNotFoundException::class, $e);
        }

        $this->expectException(WorkflowFailedException::class);
        $result = $run->getResult();
        $this->fail(sprintf("Workflow must fail. Got result %s", \print_r($result, true)));
    }

    /**
     * @group skip-on-test-server
     */
    public function testUpdateThatThrowsRetryableException()
    {
        $client = $this->createClient();
        $wf = $client->newUntypedWorkflowStub(
            'SignalExceptions.greet',
            WorkflowOptions::new()->withWorkflowRunTimeout('40 seconds')
        );

        $run = $client->start($wf);

        $wf->startUpdate('error');

        sleep(1);
        $wf->signal('exit');

        // Check history
        $e = null;
        $s = null;
        foreach ($client->getWorkflowHistory($run->getExecution()) as $event) {
            if ($event->getEventType() === EventType::EVENT_TYPE_WORKFLOW_TASK_FAILED) {
                $e = $event;
                continue;
            }

            if ($event->getEventType() === EventType::EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED) {
                $s = $event;
                continue;
            }

            if ($event->getEventType() === EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED) {
                $this->fail('Workflow must not complete');
            }
        }

        $this->assertNotNull($e);
        $this->assertNotNull($s);
    }

    /**
     * @group skip-on-test-server
     */
    public function testUpdateThatThrowsCustomError()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(UpdateExceptionsWorkflow::class);
        $run = $client->start($wf);

        try {
            $this->expectException(WorkflowUpdateException::class);
            $wf->failWithName('test1');
        } finally {
            $wf->exit();
            $this->assertSame(['test1'], $run->getResult());
        }
    }

    /**
     * @group skip-on-test-server
     */
    public function testUpdateThatThrowsInvalidArgumentException()
    {
        try {
            $client = $this->createClient();
            $wf = $client->newWorkflowStub(UpdateExceptionsWorkflow::class);
            $run = $client->start($wf);
            $this->expectException(WorkflowUpdateException::class);
            $wf->failInvalidArgument('test1');
        } finally {
            $wf->exit();
            $this->assertSame(['invalidArgument test1'], $run->getResult());
        }
    }

    /**
     * @group skip-on-test-server
     */
    public function testUpdateThatThrowsInternalException()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(UpdateExceptionsWorkflow::class);
        $client->startWithSignal($wf, 'failActivity', ['foo']);

        try {
            $this->expectException(WorkflowUpdateException::class);
            $wf->failActivity('foo');
        } finally {
            $wf->exit();
        }
    }
}
