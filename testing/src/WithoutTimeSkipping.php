<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Client\GRPC\StatusCode;
use Temporal\Exception\Client\ServiceClientException;

trait WithoutTimeSkipping
{
    private ?TestService $testService = null;
    private bool $managesTimeSkipping = false;

    protected function setUp(): void
    {
        $this->testService = TestService::create(
            \getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233',
        );

        try {
            $this->testService->lockTimeSkipping();
            $this->managesTimeSkipping = true;
        } catch (ServiceClientException $e) {
            if ($e->getCode() !== StatusCode::UNIMPLEMENTED) {
                throw $e;
            }

            $this->testService = null;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->managesTimeSkipping && $this->testService !== null) {
            $this->testService->unlockTimeSkipping();
        }

        parent::tearDown();
    }
}
