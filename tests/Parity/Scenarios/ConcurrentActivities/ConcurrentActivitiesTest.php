<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ConcurrentActivities;

use Temporal\Tests\Parity\Framework\AbstractParityScenarioTest;

final class ConcurrentActivitiesTest extends AbstractParityScenarioTest
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
        // TODO(parity-concurrent-activities): Go SDK batches workflow task processing across
        // parallel activity completions differently from PHP/Java — produces fewer
        // WORKFLOW_TASK_SCHEDULED/STARTED/COMPLETED cycles in the captured history for the
        // same business outcome. Java vs PHP pass; Go vs PHP needs either an order-tolerant
        // comparison or a synchronous-await workflow shape on the PHP side. Skipping Go pair
        // until that's resolved.
        return null;
    }
}
