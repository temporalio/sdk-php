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

#[CoversClass(TranscriptWriter::class)]
#[UsesClass(TranscriptSection::class)]
#[UsesClass(TranscriptReader::class)]
#[UsesClass(TranscriptLine::class)]
#[UsesClass(MalformedTranscriptException::class)]
final class TranscriptWriterTestCase extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/temporal-transcript-test-' . \getmypid() . '-' . \uniqid();
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

    public function testWriteLogProducesParseableLine(): void
    {
        $writer = new TranscriptWriter($this->directory . '/log.log');
        $writer->writeLog('info', 'hello world', ['key' => 'value']);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $logs = $reader->findBySection(TranscriptSection::LOG);
        self::assertCount(1, $logs);
        self::assertSame('info', $logs[0]->attributes['level']);
        self::assertSame('hello world', $logs[0]->attributes['message']);
        self::assertSame(['key' => 'value'], $logs[0]->payload);
    }

    public function testMultiLineContextIsEscapedOnOneLine(): void
    {
        $writer = new TranscriptWriter($this->directory . '/log.log');
        $writer->writeLog('warning', "line1\nline2\rline3", []);
        $writer->flush();

        $raw = \file_get_contents($writer->getPath());
        $bodyLines = \array_values(\array_filter(\explode("\n", $raw), static fn(string $l): bool => $l !== ''));
        $logLine = null;
        foreach ($bodyLines as $line) {
            if (\str_contains($line, '"section":"LOG"')) {
                $logLine = $line;
                break;
            }
        }
        self::assertNotNull($logLine);
        self::assertStringNotContainsString("\n", $logLine);
        self::assertStringContainsString('line1\\nline2\\rline3', $logLine);
    }

    public function testWriteWireRoundTripsFrameBytes(): void
    {
        $writer = new TranscriptWriter($this->directory . '/wire.log');
        $frame = '{"command":"InvokeActivity","payloads":["abc"]}';
        $writer->writeWireInbound($frame, ['tickTime' => '2026-05-13'], 42);
        $writer->writeWireOutbound($frame, 42);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $inbound = $reader->findBySection(TranscriptSection::WIRE_INBOUND);
        $outbound = $reader->findBySection(TranscriptSection::WIRE_OUTBOUND);
        self::assertCount(1, $inbound);
        self::assertCount(1, $outbound);
        self::assertSame(42, $inbound[0]->attributes['frame_id']);
        self::assertSame(\strlen($frame), $inbound[0]->attributes['bytes']);
        $decoded = $inbound[0]->payload['body']['value'] ?? null;
        self::assertIsArray($decoded);
        self::assertSame('InvokeActivity', $decoded['command']);
    }

    public function testWriteExceptionCarriesClassAndTrace(): void
    {
        $writer = new TranscriptWriter($this->directory . '/exc.log');
        $writer->writeException('activity_throw', ['attempt' => 2], new \RuntimeException('boom'));
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $exceptions = $reader->findBySection(TranscriptSection::EXCEPTION);
        self::assertCount(1, $exceptions);
        self::assertSame('activity_throw', $exceptions[0]->attributes['phase']);
        self::assertSame(2, $exceptions[0]->attributes['attempt']);
        self::assertSame(\RuntimeException::class, $exceptions[0]->payload['class']);
        self::assertSame('boom', $exceptions[0]->payload['message']);
        self::assertNotSame('', $exceptions[0]->payload['trace']);
    }

    public function testWriteFatalCarriesThrowableMetadata(): void
    {
        $writer = new TranscriptWriter($this->directory . '/fatal.log');
        $writer->writeFatal(new \Error('boom'));
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $fatal = $reader->findBySection(TranscriptSection::FATAL);
        self::assertCount(1, $fatal);
        self::assertSame(\Error::class, $fatal[0]->attributes['class']);
        self::assertSame('boom', $fatal[0]->payload['message']);
    }

    public function testWriteHistoryEventSerializesProtoJson(): void
    {
        $writer = new TranscriptWriter($this->directory . '/history.log');
        $writer->writeHistoryEvent('wf-1', 'run-1', ['event_id' => 5, 'event_type' => 'ActivityTaskScheduled'], '{"event":"abc"}');
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $history = $reader->findBySection(TranscriptSection::HISTORY);
        self::assertCount(1, $history);
        self::assertSame('wf-1', $history[0]->attributes['workflow_id']);
        self::assertSame(5, $history[0]->attributes['event_id']);
        self::assertSame('ActivityTaskScheduled', $history[0]->attributes['event_type']);
        self::assertSame('{"event":"abc"}', $history[0]->payload['attrs']);
    }

    public function testEveryLineCarriesPidAndIsoTimestamp(): void
    {
        $writer = new TranscriptWriter($this->directory . '/all.log');
        $writer->writeLog('info', 'one', []);
        $writer->writeMeta('event_two', ['k' => 'v']);
        $writer->flush();

        $reader = new TranscriptReader($this->directory);
        $lines = $reader->getLines();
        self::assertGreaterThanOrEqual(2, \count($lines));
        $processId = \getmypid();
        foreach ($lines as $line) {
            self::assertSame($processId, $line->processId);
            self::assertNotNull($line->timestamp);
        }
    }

    public function testConcurrentWritersUnderLockExProduceWellFormedLines(): void
    {
        $path = $this->directory . '/concurrent.log';
        $childCount = 2;
        $writesPerChild = 50;
        $baseDir = \dirname(__DIR__, 3);
        $autoloadPath = \var_export($baseDir . '/vendor/autoload.php', true);
        $childPaths = [];
        $processes = [];
        for ($i = 0; $i < $childCount; $i++) {
            $script = $this->directory . "/child-{$i}.php";
            \file_put_contents($script, <<<PHP
                <?php
                require {$autoloadPath};
                \$writer = new \\Temporal\\Tests\\Acceptance\\App\\Logger\\TranscriptWriter('{$path}');
                for (\$j = 0; \$j < {$writesPerChild}; \$j++) {
                    \$writer->writeLog('info', "child-{$i}-write-\$j", []);
                }
                \$writer->flush();
                PHP);
            $childPaths[] = $script;
            $processes[] = \proc_open(['php', $script], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            // close pipes immediately to let child exit
            foreach ($pipes as $pipe) {
                if (\is_resource($pipe)) {
                    \fclose($pipe);
                }
            }
        }
        foreach ($processes as $process) {
            if (\is_resource($process)) {
                \proc_close($process);
            }
        }
        foreach ($childPaths as $script) {
            @\unlink($script);
        }
        $reader = new TranscriptReader($this->directory);
        $logs = $reader->findBySection(TranscriptSection::LOG);
        self::assertSame($childCount * $writesPerChild, \count($logs));
        foreach ($logs as $line) {
            self::assertStringStartsWith('child-', (string) $line->attributes['message']);
        }
    }

    public function testReaderRejectsMalformedLine(): void
    {
        $path = $this->directory . '/bad.log';
        \file_put_contents($path, "this line does not match the schema\n");
        $reader = new TranscriptReader($this->directory);

        $this->expectException(MalformedTranscriptException::class);
        $reader->getLines();
    }

}
