<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Client;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\SagaWorkflow;
use Temporal\WorkflowClient;

class SagaTestCase extends TestCase
{
    public function testGetResult()
    {
        $w = $this->createClient();
        $saga = $w->newWorkflowStub(SagaWorkflow::class);

        try {
            $saga->run();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
        }
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
