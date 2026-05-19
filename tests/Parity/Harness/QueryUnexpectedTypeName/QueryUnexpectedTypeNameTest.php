<?php

declare(strict_types=1);

namespace TemporalTestsParityHarnessQueryUnexpectedTypeName;

use Temporal\Tests\Parity\Framework\AbstractParityScenarioTest;

final class QueryUnexpectedTypeNameTest extends AbstractParityScenarioTest
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
