<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Client;

use Carbon\CarbonInterval;
use Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsRequest;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Tests\TestCase;
use Temporal\WorkflowClient;

class ServiceClientTestCase extends TestCase
{
    public function testTimeoutException()
    {
        $ds = new ListClosedWorkflowExecutionsRequest();
        $ds->setNamespace('default');

        $this->expectException(TimeoutException::class);
        $this->createClient()->getServiceClient()->ListClosedWorkflowExecutions(
            $ds,
            Context::default()->withTimeout(CarbonInterval::millisecond(1))
        );
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
