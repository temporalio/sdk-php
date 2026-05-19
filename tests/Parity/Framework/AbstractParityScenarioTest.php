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

    protected function fixtureGo(): ?string
    {
        return null;
    }

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

    #[Test]
    public function normalizedGoMatchesPhp(): void
    {
        $goFixture = $this->fixtureGo();
        if ($goFixture === null) {
            self::markTestSkipped(
                "Scenario {$this->scenarioLabel()} has no Go fixture yet "
                . '(see .ai-factory/plans/parity-go-sdk-support.md, task T6).',
            );
        }

        HistoryLoader::requireExists($goFixture);
        HistoryLoader::requireExists($this->fixturePhp());

        $logger = $this->maybeLogger();
        $registry = NormalizerRegistry::default($logger);

        $go = $registry->normalize(HistoryLoader::loadJson($goFixture, Source::GO));
        $php = $registry->normalize(HistoryLoader::loadJson($this->fixturePhp(), Source::PHP));

        self::assertNotEmpty($go, "Go fixture for {$this->scenarioLabel()} decoded to zero events.");
        self::assertNotEmpty($php, "PHP fixture for {$this->scenarioLabel()} decoded to zero events.");

        $logger?->debug('normalized event counts', [
            'scenario' => $this->scenarioLabel(),
            'go' => \count($go),
            'php' => \count($php),
        ]);

        self::assertEquals(
            $go,
            $php,
            "Normalized {$this->scenarioLabel()} Go vs PHP histories diverge. Inspect the array diff "
            . 'to identify the field that still varies, then either add a Go-specific rule in '
            . 'GoSdkNormalizer or treat the divergence as a real upstream gap.',
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
