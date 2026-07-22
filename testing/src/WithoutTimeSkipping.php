<?php

declare(strict_types=1);

namespace Temporal\Testing;

trait WithoutTimeSkipping
{
    private TestService $testService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testService = TestService::create(TemporalServer::address());
        $this->testService->lockTimeSkipping();
    }

    protected function tearDown(): void
    {
        try {
            $this->testService->unlockTimeSkipping();
        } finally {
            parent::tearDown();
        }
    }
}
