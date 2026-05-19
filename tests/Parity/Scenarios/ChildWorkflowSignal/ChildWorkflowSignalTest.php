<?php

declare(strict_types=1);

namespace TemporalTestsParityHarnessChildWorkflowSignal;

use Temporal\Tests\Parity\Framework\AbstractParityScenarioTest;

final class ChildWorkflowSignalTest extends AbstractParityScenarioTest
{
    protected function fixtureJava(): string
    {
        return __DIR__ . '/fixtures/java.json';
    }

    protected function fixturePhp(): string
    {
        return __DIR__ . '/fixtures/php.json';
    }

    protected function fixtureGo(): ?string
    {
        return __DIR__ . '/fixtures/go.json';
    }
}
