<?php

namespace Temporal\Tests\Client;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\TestCase;

class ActivityCompletionClientTestCase extends TestCase
{
    public function testCompletedExternallyByToken()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('ExternalCompleteWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../taskToken');

        $act = $w->newActivityCompletionClient();

        $act->completeByToken($taskToken, 'Completed Externally');

        $this->assertSame('Completed Externally', $simple->getResult(0));
    }

    // todo: by id

    /**
     * @return WorkflowClient
     */
    private function createClient(): WorkflowClient
    {
        $sc = ServiceClient::createInsecure('localhost:7233');

        return new WorkflowClient($sc);
    }
}
