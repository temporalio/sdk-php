<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Client;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Tests\TestCase;

class FailureTestCase extends TestCase
{
    public function testSimpleFailurePropagation()
    {
        $w = $this->createClient();
        $ex = $w->newUntypedWorkflowStub('ExceptionalWorkflow');

        $e = $ex->start();
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        $this->expectException(WorkflowFailedException::class);
        $this->assertSame('OK', $ex->getResult(0));
        // todo: verify parent exceptions
    }

    public function testActivityFailurePropagation()
    {
        $w = $this->createClient();
        $ex = $w->newUntypedWorkflowStub('ExceptionalActivityWorkflow');

        $e = $ex->start();
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        $this->expectException(WorkflowFailedException::class);
        $ex->getResult(0);
        // todo: verify parent exceptions
    }

    public function testChildWorkflowFailurePropagation()
    {
        $w = $this->createClient();
        $ex = $w->newUntypedWorkflowStub('ComplexExceptionalWorkflow');

        $e = $ex->start();
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        //$this->expectException(WorkflowFailedException::class);
        $ex->getResult(0);
        // todo: verify parent exceptions
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
