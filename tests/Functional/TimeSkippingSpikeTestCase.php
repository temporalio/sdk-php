<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Testing\WorkflowTestCase;

final class TimeSkippingSpikeTestCase extends WorkflowTestCase
{
    public function testLockThenUnlockWithSleepFastForwardsServerTime(): void
    {
        $this->testingService->lockTimeSkipping();

        $before = $this->testingService->getCurrentTime()->getTimestamp();
        $this->testingService->unlockTimeSkippingWithSleep(5);
        $after = $this->testingService->getCurrentTime()->getTimestamp();

        $this->testingService->unlockTimeSkipping();

        self::assertGreaterThanOrEqual(5, $after - $before);
        self::assertLessThan(15, $after - $before);
    }
}
