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
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Tests\Workflow\SignalExceptionsWorkflow;

/**
 * @group client
 * @group functional
 */
class FailureTestCase extends ClientTestCase
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

    public function testSignalThatThrowsCustomError()
    {
        $client = $this->createClient();
        $wf = $client->newWorkflowStub(SignalExceptionsWorkflow::class);

        $run = $client->start($wf);

        $wf->failWithName('test1');

        try {
            // The next
            sleep(1);
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
            sleep(5);
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
}
