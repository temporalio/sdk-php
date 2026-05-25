<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Logger;

use JetBrains\PhpStorm\Language;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Temporal\Testing\Transcript\MalformedTranscriptException;
use Temporal\Testing\Transcript\TranscriptLine;
use Temporal\Testing\Transcript\TranscriptReader;
use Temporal\Testing\Transcript\TranscriptSection;
use Temporal\Testing\Transcript\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\FatalHandler;

#[CoversClass(FatalHandler::class)]
#[UsesClass(TranscriptWriter::class)]
#[UsesClass(TranscriptSection::class)]
#[UsesClass(TranscriptReader::class)]
#[UsesClass(TranscriptLine::class)]
#[UsesClass(MalformedTranscriptException::class)]
final class FatalHandlerTestCase extends TestCase
{
    use TranscriptTestSupport;

    public function testUserErrorIsRecordedAsFatalViaShutdownFunction(): void
    {
        $logFile = $this->directory . '/fatal.log';
        $script = $this->buildFixtureScript($logFile, "trigger_error('intentional fatal', E_USER_ERROR);");
        $this->executeFixture($script);

        $reader = new TranscriptReader($this->directory);
        $fatal = $reader->findBySection(TranscriptSection::FATAL);
        self::assertNotEmpty($fatal, $this->diagnostic('Expected a [FATAL] line', $logFile));
        self::assertStringContainsString('intentional fatal', (string) ($fatal[0]->payload['message'] ?? ''));
    }

    public function testUncaughtErrorIsRecordedAsFatalViaExceptionHandler(): void
    {
        $logFile = $this->directory . '/uncaught.log';
        $script = $this->buildFixtureScript($logFile, "throw new \\Error('uncaught fatal');");
        $this->executeFixture($script);

        $reader = new TranscriptReader($this->directory);
        $fatal = $reader->findBySection(TranscriptSection::FATAL);
        self::assertNotEmpty($fatal, $this->diagnostic('FATAL marker missing', $logFile));
        self::assertSame(\Error::class, $fatal[0]->attributes['class']);
        self::assertSame('uncaught fatal', (string) $fatal[0]->payload['message']);
    }

    public function testWritesPriorToFatalArePreserved(): void
    {
        $logFile = $this->directory . '/preserved.log';
        $script = $this->buildFixtureScript(
            $logFile,
            "\$writer->writeTestBoundary(\\Temporal\\Tests\\Acceptance\\App\\Logger\\TranscriptSection::TEST_START, ['name' => 'pre-fatal']);\n"
            . "\$writer->writeLog('info', 'about to die', []);\n"
            . "trigger_error('boom', E_USER_ERROR);",
        );
        $this->executeFixture($script);

        $reader = new TranscriptReader($this->directory);
        $boundaries = $reader->findBySection(TranscriptSection::TEST_START);
        $logs = $reader->findBySection(TranscriptSection::LOG);
        $fatal = $reader->findBySection(TranscriptSection::FATAL);
        self::assertNotEmpty($boundaries, $this->diagnostic('TEST_START not preserved across fatal', $logFile));
        self::assertNotEmpty($logs, $this->diagnostic('LOG not preserved across fatal', $logFile));
        self::assertNotEmpty($fatal, $this->diagnostic('FATAL marker missing', $logFile));
    }

    private function diagnostic(string $message, string $logFile): string
    {
        return $message
            . "\nfixture stdout/stderr:\n" . $this->lastFixtureOutput()
            . "\ntranscript content:\n" . @\file_get_contents($logFile);
    }

    private function buildFixtureScript(string $logFile, #[Language("PHP")]string $body): string
    {
        $baseDir = \dirname(__DIR__, 3);
        $autoloadPath = \var_export($baseDir . '/vendor/autoload.php', true);
        $logFileExport = \var_export($logFile, true);
        return <<<PHP
            <?php
            declare(strict_types=1);
            require {$autoloadPath};
            \$writer = new \\Temporal\\Tests\\Acceptance\\App\\Logger\\TranscriptWriter({$logFileExport});
            \\Temporal\\Tests\\Acceptance\\App\\Runtime\\FatalHandler::register(\$writer);
            {$body}
            PHP;
    }

    /** @var list<string> */
    private array $lastFixtureOutput = [];

    private function executeFixture(string $script): void
    {
        $scriptPath = $this->directory . '/fixture-' . \uniqid('', true) . '.php';
        \file_put_contents($scriptPath, $script);
        $command = \escapeshellarg(\PHP_BINARY) . ' ' . \escapeshellarg($scriptPath) . ' 2>&1';
        \exec($command, $output, $exitCode);
        $this->lastFixtureOutput = $output;
        self::assertNotSame(0, $exitCode, 'Fixture process should exit non-zero on fatal; output: ' . \implode("\n", $output));
    }

    private function lastFixtureOutput(): string
    {
        return \implode("\n", $this->lastFixtureOutput);
    }
}
