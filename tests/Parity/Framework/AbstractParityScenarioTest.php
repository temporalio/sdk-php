<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Temporal\Worker\Logger\StderrLogger;

abstract class AbstractParityScenarioTest extends TestCase
{
    abstract protected function fixtureJava(): string;

    abstract protected function fixturePhp(): string;

    protected function scenarioLabel(): string
    {
        return (new \ReflectionClass(static::class))->getShortName();
    }

    #[Test]
    public function normalizedJavaAndPhpHistoriesMatch(): void
    {
        HistoryLoader::requireExists($this->fixtureJava());
        HistoryLoader::requireExists($this->fixturePhp());

        $logger = $this->maybeLogger();
        $registry = NormalizerRegistry::default($logger);

        $java = $registry->normalize(HistoryLoader::loadJson($this->fixtureJava(), Source::JAVA));
        $php = $registry->normalize(HistoryLoader::loadJson($this->fixturePhp(), Source::PHP));

        self::assertNotEmpty($java, "Java fixture for {$this->scenarioLabel()} decoded to zero events.");
        self::assertNotEmpty($php, "PHP fixture for {$this->scenarioLabel()} decoded to zero events.");

        $logger?->debug('normalized event counts', [
            'scenario' => $this->scenarioLabel(),
            'java' => \count($java),
            'php' => \count($php),
        ]);

        self::assertEquals(
            $java,
            $php,
            "Normalized {$this->scenarioLabel()} histories diverge. Inspect the array diff "
            . 'to identify the field that still varies between SDKs, then either add a new '
            . 'NormalizerRegistry rule or treat the divergence as a real upstream gap.',
        );
    }

    private function maybeLogger(): ?LoggerInterface
    {
        if (\getenv('PARITY_DEBUG') !== '1') {
            return null;
        }

        return new StderrLogger();
    }
}
