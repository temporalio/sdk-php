<?php

declare(strict_types=1);

namespace Temporal\Testing;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

class WorkflowTestCase extends TestCase
{
    private WorkflowClient $workflowClient;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create('localhost:7233')
        );
        parent::setUp();
    }

    protected function newWorkflowStub(string $class, WorkflowOptions $options = null): object
    {
        return $this->workflowClient->newWorkflowStub($class, $options);
    }

}
