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
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Tests\Workflow\SagaWorkflow;

/**
 * @group client
 * @group functional
 */
class SagaTestCase extends AbstractClient
{
    public function testGetResult()
    {
        $client = $this->createClient();
        $saga = $client->newWorkflowStub(SagaWorkflow::class);

        $run = $client->start($saga);

        try {
            $run->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
        }

        $this->assertHistoryContainsActivity($client, $run->getExecution(), 'SimpleActivity.echo');
        $this->assertHistoryContainsActivity($client, $run->getExecution(), 'SimpleActivity.lower');
        $this->assertHistoryContainsActivity($client, $run->getExecution(), 'SimpleActivity.prefix');
    }
}
