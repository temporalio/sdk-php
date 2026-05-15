<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\ActivityRetry;

use Temporal\Tests\Parity\Framework\AbstractParityScenarioTest;

final class ActivityRetryTest extends AbstractParityScenarioTest
{
    protected function fixtureJava(): string
    {
        return __DIR__ . '/fixtures/java.json';
    }

    protected function fixturePhp(): string
    {
        return __DIR__ . '/fixtures/php.json';
    }
}
