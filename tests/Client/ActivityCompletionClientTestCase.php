<?php

namespace Temporal\Tests\Client;

use Temporal\Api\Workflow\V1\PendingActivityInfo;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\WorkflowClient;
use Temporal\Exception\Client\ActivityCompletionFailureException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\TestCase;

class ActivityCompletionClientTestCase extends TestCase
{
    public function testCompleteAsyncActivityById()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../activityId');
        $data = json_decode(file_get_contents(__DIR__ . '/../activityId'));
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $act->complete($data->id, null, $data->activityId, 'Completed Externally by ID');

        $this->assertSame('Completed Externally by ID', $simple->getResult(0));
    }

    public function testCompleteAsyncActivityByIdExplicit()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../activityId');
        $data = json_decode(file_get_contents(__DIR__ . '/../activityId'));
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $act->complete($data->id, $data->runId, $data->activityId, 'Completed Externally by ID explicit');

        $this->assertSame('Completed Externally by ID explicit', $simple->getResult(0));
    }

    public function testCompleteAsyncActivityByIdInvalid()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../activityId');
        $data = json_decode(file_get_contents(__DIR__ . '/../activityId'));
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $this->expectException(ActivityCompletionFailureException::class);
        $act->complete($data->id, null, "invalid activity id", 'Completed Externally by ID');
    }

    public function testCompleteAsyncActivityByToken()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $act->completeByToken($taskToken, 'Completed Externally');

        $this->assertSame('Completed Externally', $simple->getResult(0));
    }

    public function testCompleteAsyncActivityByTokenInvalid()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $this->expectException(ActivityCompletionFailureException::class);
        $act->completeByToken('broken' . $taskToken, 'Completed Externally');
    }

    public function testCompleteAsyncActivityByTokenExceptionally()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $act->completeExceptionallyByToken($taskToken, new \Error('manually triggered'));
        try {
            $simple->getResult(0);
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            $this->assertStringContainsString('\AsyncActivityWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('manually triggered', $e->getPrevious()->getMessage());
        }
    }

    public function testCompleteAsyncActivityByTokenExceptionallyById()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $data = json_decode(file_get_contents(__DIR__ . '/../activityId'));
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

        $act->completeExceptionally(
            $data->id,
            $data->runId,
            $data->activityId,
            new \Error('manually triggered 2')
        );

        try {
            $simple->getResult(0);
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            $this->assertStringContainsString('\AsyncActivityWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('manually triggered 2', $e->getPrevious()->getMessage());
        }
    }

    public function testHeartBeatByID()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $data = json_decode(file_get_contents(__DIR__ . '/../activityId'));
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

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
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('AsyncActivityWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../activityId');

        $act = $w->newActivityCompletionClient();

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

    /**
     * @return WorkflowClient
     */
    private function createClient(): WorkflowClient
    {
        $sc = ServiceClient::createInsecure('localhost:7233');

        return new WorkflowClient($sc);
    }
}
