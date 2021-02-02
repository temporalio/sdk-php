<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\Functional\FunctionalTestCase;
use Temporal\Workflow\WorkflowExecution;

/**
 * @group client
 */
abstract class ClientTestCase extends FunctionalTestCase
{
    /**
     * @param string $connection
     * @return WorkflowClient
     */
    protected function createClient(string $connection = 'localhost:7233'): WorkflowClient
    {
        return new WorkflowClient(
            ServiceClient::create($connection)
        );
    }

    protected function assertHistoryContains(
        WorkflowClient $client,
        WorkflowExecution $e,
        callable $checker
    ) {
        $arg = new GetWorkflowExecutionHistoryRequest();
        $arg->setNamespace('default');
        $arg->setExecution($e->toProtoWorkflowExecution());

        $r = $client->getServiceClient()->GetWorkflowExecutionHistory($arg);

        foreach ($r->getHistory()->getEvents() as $item) {
            if ($checker($item)) {
                $this->assertTrue(true, 'found match');
                return true;
            }
        }

        $this->fail('history does not match');
    }
}
