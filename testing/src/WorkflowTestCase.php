<?php

declare(strict_types=1);

namespace Temporal\Testing;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class WorkflowTestCase extends TestCase
{
    protected WorkflowClient $workflowClient;
    protected TestService $testingService;

    protected function setUp(): void
    {
        $temporalAddress = \getenv('TEMPORAL_ADDRESS');
        $temporalAddress = \is_string($temporalAddress) && !empty($temporalAddress) ? $temporalAddress : '127.0.0.1:7233';
        $this->workflowClient = new WorkflowClient(ServiceClient::create($temporalAddress));
        $this->testingService = TestService::create($temporalAddress);

        parent::setUp();
    }
}
