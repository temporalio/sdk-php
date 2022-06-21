<?php

declare(strict_types=1);

namespace Temporal\Testing;

trait WithoutTimeSkipping
{
    private TestService $testService;

    protected function setUp(): void
    {
        $this->testService = TestService::create('localhost:7233');
        $this->testService->lockTimeSkipping();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->testService->unlockTimeSkipping();
        parent::tearDown();
    }
}
