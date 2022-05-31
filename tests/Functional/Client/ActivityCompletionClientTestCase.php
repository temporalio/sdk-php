<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Api\Workflow\V1\PendingActivityInfo;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest;
use Temporal\Exception\Client\ActivityCompletionFailureException;
use Temporal\Exception\Client\ActivityNotExistsException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;

/**
 * @group client
 * @group functional
 */
class ActivityCompletionClientTestCase extends ClientTestCase
{
    public function testCompleteAsyncActivityById()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../activityId');
        $data = json_decode(file_get_contents(__DIR__ . '/../../activityId'));
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->complete($data->id, null, $data->activityId, 'Completed Externally by ID');

        $this->assertSame('Completed Externally by ID', $simple->getResult(0));
    }

    public function testCompleteAsyncActivityByIdExplicit()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../activityId');
        $data = json_decode(file_get_contents(__DIR__ . '/../../activityId'));
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->complete($data->id, $data->runId, $data->activityId, 'Completed Externally by ID explicit');

        $this->assertSame('Completed Externally by ID explicit', $simple->getResult(0));
    }

    public function testCompleteAsyncActivityByIdInvalid()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../activityId');
        $data = json_decode(file_get_contents(__DIR__ . '/../../activityId'));
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        try {
            $act->complete($data->id, null, "invalid activity id", 'Completed Externally by ID');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ActivityNotExistsException::class, $e);
        }

        $act->complete($data->id, $data->runId, $data->activityId, 'Completed Externally by ID explicit');
    }

    public function testCompleteAsyncActivityByToken()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->completeByToken($taskToken, 'Completed Externally');

        $this->assertSame('Completed Externally', $simple->getResult(0));
    }

    public function testCompleteAsyncActivityByTokenInvalid()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../../taskToken');

        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        try {
            $act->completeByToken('broken' . $taskToken, 'Completed Externally');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ActivityCompletionFailureException::class, $e);
        }

        $act->completeByToken($taskToken, 'Completed Externally by broken token');
    }

    public function testCompleteAsyncActivityByTokenExceptionally()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->completeExceptionallyByToken($taskToken, new \Error('manually triggered'));
        try {
            $simple->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            $this->assertStringContainsString('AsyncActivityWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('manually triggered', $e->getPrevious()->getMessage());
        }
    }

    public function testCompleteAsyncActivityByTokenExceptionallyById()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(2);
        $this->assertFileExists(__DIR__ . '/../../taskToken');
        $data = json_decode(file_get_contents(__DIR__ . '/../../activityId'));
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->completeExceptionally(
            $data->id,
            $data->runId,
            $data->activityId,
            new \Error('manually triggered 2')
        );

        try {
            $simple->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            $this->assertStringContainsString('AsyncActivityWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('manually triggered 2', $e->getPrevious()->getMessage());
        }
    }

    public function testHeartBeatByID()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../taskToken');
        $data = json_decode(file_get_contents(__DIR__ . '/../../activityId'));
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->recordHeartbeat(
            $data->id,
            $data->runId,
            $data->activityId,
            'heardbeatdata'
        );

        $r = new DescribeWorkflowExecutionRequest();
        $r->setExecution($simple->getExecution()->toProtoWorkflowExecution());
        $r->setNamespace('default');

        $d = $this->createClient()->getServiceClient()->DescribeWorkflowExecution($r);

        /** @var PendingActivityInfo $pa */
        $pa = $d->getPendingActivities()->offsetGet(0);
        $this->assertSame(
            json_encode('heardbeatdata'),
            $pa->getHeartbeatDetails()->getPayloads()->offsetGet(0)->getData()
        );

        $act->complete(
            $data->id,
            $data->runId,
            $data->activityId,
            'Completed Externally'
        );

        $simple->getResult(0);
    }

    public function testHeartBeatByToken()
    {
        $client = $this->createClient();
        $simple = $client->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $client->start($simple, 'hello world');
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../taskToken');
        unlink(__DIR__ . '/../../activityId');

        $act = $client->newActivityCompletionClient();

        $act->recordHeartbeatByToken($taskToken, 'heardbeatdata');

        $r = new DescribeWorkflowExecutionRequest();
        $r->setExecution($simple->getExecution()->toProtoWorkflowExecution());
        $r->setNamespace('default');

        $d = $this->createClient()->getServiceClient()->DescribeWorkflowExecution($r);

        /** @var PendingActivityInfo $pa */
        $pa = $d->getPendingActivities()->offsetGet(0);
        $this->assertSame(
            json_encode('heardbeatdata'),
            $pa->getHeartbeatDetails()->getPayloads()->offsetGet(0)->getData()
        );

        $act->completeByToken($taskToken, 'Completed Externally');
        $simple->getResult(0);
    }

//    public function testCanceledActivityInWorkflow()
//    {
//        $client = $this->createClient();
//        $w = $client->newWorkflowStub(CanceledHeartbeatWorkflow::class);
//
//        /** @var WorkflowStubInterface $r */
//        $r = $w->startAsync();
//        sleep(1);
//
//        $uw = $client->newUntypedWorkflowStub('CanceledHeartbeatWorkflow')->setExecution($r->getExecution());
//        $uw->cancel();
//
//        try {
//            $r->getResult();
//            $this->fail('unreachable');
//        } catch (WorkflowFailedException $e) {
//            $this->assertInstanceOf(CanceledFailure::class, $e->getPrevious());
//        }
//    }
}
