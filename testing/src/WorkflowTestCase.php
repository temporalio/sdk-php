<?php

declare(strict_types=1);

namespace Temporal\Testing;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Testing\Interactions\WorkflowInteractions;
use Temporal\Workflow\WorkflowRunInterface;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class WorkflowTestCase extends TestCase
{
    protected WorkflowClient $workflowClient;
    protected TestService $testingService;
    protected ActivityMocker $activityMocks;
    protected WorkflowMocker $workflowMocks;
    protected DelayedCallbackScheduler $delayedCallbacks;

    protected function setUp(): void
    {
        $temporalAddress = TemporalServer::address();
        $this->testingService = TestService::create($temporalAddress);
        $this->activityMocks = new ActivityMocker();
        $this->workflowMocks = new WorkflowMocker();
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create($temporalAddress),
            interceptorProvider: new SimplePipelineProvider($this->clientInterceptors()),
        );
        $this->delayedCallbacks = new DelayedCallbackScheduler($this->workflowClient, $this->testingService);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->activityMocks->clear();
        $this->workflowMocks->clear();
        $this->assertTimeSkippingBalanced();

        parent::tearDown();
    }

    private function assertTimeSkippingBalanced(): void
    {
        $delta = $this->testingService->lockDelta();
        if ($delta === 0) {
            return;
        }

        for ($i = 0; $i < $delta; ++$i) {
            try {
                $this->testingService->unlockTimeSkipping();
            } catch (\Throwable) {
                break;
            }
        }

        self::fail(\sprintf(
            'Time-skip lock counter left unbalanced by %d: a test leaked lockTimeSkipping/unlockTimeSkipping. '
            . 'The server counter was healed for the next test.',
            $delta,
        ));
    }

    /**
     * @return list<Interceptor>
     */
    protected function clientInterceptors(): array
    {
        return [];
    }

    protected function interactions(WorkflowRunInterface $run): WorkflowInteractions
    {
        return WorkflowInteractions::of($this->workflowClient, $run);
    }
}
