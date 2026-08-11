<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Internal\Interceptor\Interceptor;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class TimeSkippingWorkflowTestCase extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->testingService->lockTimeSkipping();
    }

    protected function tearDown(): void
    {
        $this->testingService->unlockTimeSkipping();

        parent::tearDown();
    }

    /**
     * @return list<Interceptor>
     */
    protected function clientInterceptors(): array
    {
        return [new TimeLockingInterceptor($this->testingService)];
    }
}
