<?php

namespace Temporal\Tests\Client;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Tests\TestCase;

class WorkflowStubTestCase extends TestCase
{
    public function testUntypedStartAndWaitResult()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('SimpleWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        $this->assertSame('HELLO WORLD', $simple->getResult(0));
    }

    public function testStartWithSameID()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('SimpleWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        $simple2 = $w->newUntypedWorkflowStub('SimpleWorkflow', WorkflowOptions::new()->withWorkflowId($e->id));

        $this->expectException(WorkflowExecutionAlreadyStartedException::class);
        $simple2->start(['hello world']);
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
