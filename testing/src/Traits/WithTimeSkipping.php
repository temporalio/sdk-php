<?php

declare(strict_types=1);

namespace Temporal\Testing\Traits;

use Temporal\Testing\TestService;

trait WithTimeSkipping
{
    private TestService $testService;

    protected function setUp(): void
    {
        $this->testService = TestService::create('localhost:7233');
        $this->testService->unlockTimeSkipping();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->testService->lockTimeSkipping();
        parent::tearDown();
    }
}
