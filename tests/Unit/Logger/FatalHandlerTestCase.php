<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Logger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Temporal\Tests\Acceptance\App\Logger\MalformedTranscriptException;
use Temporal\Tests\Acceptance\App\Logger\TranscriptLine;
use Temporal\Tests\Acceptance\App\Logger\TranscriptReader;
use Temporal\Tests\Acceptance\App\Logger\TranscriptSection;
use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\FatalHandler;

#[CoversClass(FatalHandler::class)]
#[UsesClass(TranscriptWriter::class)]
#[UsesClass(TranscriptSection::class)]
#[UsesClass(TranscriptReader::class)]
#[UsesClass(TranscriptLine::class)]
#[UsesClass(MalformedTranscriptException::class)]
final class FatalHandlerTestCase extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/fatal-handler-' . \getmypid() . '-' . \uniqid();
        \mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->directory . '/*') ?: [] as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
        @\rmdir($this->directory);
    }

    public function testUserErrorIsRecordedAsFatalViaShutdownFunction(): void
    {
        $logFile = $this->directory . '/fatal.log';
        $script = $this->buildFixtureScript($logFile, "trigger_error('intentional fatal', E_USER_ERROR);");
        $this->executeFixture($script);

        $reader = new TranscriptReader($this->directory);
        $fatal = $reader->findBySection(TranscriptSection::FATAL);
        self::assertNotEmpty($fatal, 'Expected a [FATAL] line; transcript content: ' . \file_get_contents($logFile));
        self::assertStringContainsString('intentional fatal', (string) ($fatal[0]->payload['message'] ?? ''));
    }

    public function testUncaughtErrorIsRecordedAsFatalViaExceptionHandler(): void
    {
        $logFile = $this->directory . '/uncaught.log';
        $script = $this->buildFixtureScript($logFile, "throw new \\Error('uncaught fatal');");
        $this->executeFixture($script);

        $reader = new TranscriptReader($this->directory);
        $fatal = $reader->findBySection(TranscriptSection::FATAL);
        self::assertNotEmpty($fatal);
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
        self::assertNotEmpty($boundaries, 'TEST_START not preserved across fatal');
        self::assertNotEmpty($logs, 'LOG not preserved across fatal');
        self::assertNotEmpty($fatal, 'FATAL marker missing');
    }

    private function buildFixtureScript(string $logFile, string $body): string
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

    private function executeFixture(string $script): void
    {
        $scriptPath = $this->directory . '/fixture-' . \uniqid() . '.php';
        \file_put_contents($scriptPath, $script);
        $command = 'php ' . \escapeshellarg($scriptPath) . ' 2>&1';
        \exec($command, $output, $exitCode);
        self::assertNotSame(0, $exitCode, 'Fixture process should exit non-zero on fatal; output: ' . \implode("\n", $output));
    }
}
