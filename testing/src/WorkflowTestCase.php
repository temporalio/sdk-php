<?php

declare(strict_types=1);

namespace Temporal\Testing;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;

class WorkflowTestCase extends TestCase
{
    protected WorkflowClient $workflowClient;
    protected TestService $testingService;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(ServiceClient::create($this->testServiceHost()));
        $this->testingService = TestService::create($this->testServiceHost());

        parent::setUp();
    }

    protected function testServiceHost(): string
    {
        return 'localhost:7233';
    }
}
