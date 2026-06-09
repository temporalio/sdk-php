<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final class TranscriptWriter
{
    private const SIZE_CAP_BYTES = 50 * 1024 * 1024;

    private const JSON_FLAGS = \JSON_UNESCAPED_UNICODE
        | \JSON_UNESCAPED_SLASHES
        | \JSON_INVALID_UTF8_SUBSTITUTE;

    /** @var resource|null */
    private $fileDescriptor;

    private string $currentPath;

    private int $sequence = 0;

    private int $rotationCounter = 0;

    private readonly int $processId;

    private readonly LoggerInterface $stderr;

    private readonly Filesystem $filesystem;

    private bool $inWrite = false;

    /**
     * @param non-empty-string $path
     */
    public function __construct(string $path, ?LoggerInterface $stderr = null)
    {
        $this->processId = \getmypid() ?: 0;
        $this->currentPath = $path;
        $this->stderr = $stderr ?? new NullLogger();
        $this->filesystem = new Filesystem();
        $this->openFileDescriptor($path);
        $this->writeMeta('writer_initialized', [
            'path' => $path,
            'worker_start_epoch_ms' => TranscriptPaths::currentEpochMs(),
        ]);
        \register_shutdown_function(function (): void {
            if ($this->fileDescriptor !== null) {
                @\fflush($this->fileDescriptor);
            }
        });
    }

    public function getPath(): string
    {
        return $this->currentPath;
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function write(
        TranscriptSection $section,
        array $attributes = [],
        mixed $payload = null,
    ): void {
        if ($this->inWrite) {
            return;
        }
        $this->inWrite = true;
        try {
            $this->doWrite($section, $attributes, $payload);
        } catch (\Throwable $e) {
            $this->stderr->error('transcript-writer-internal-error', [
                'class' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->inWrite = false;
        }
    }

    public function writeLog(string $level, string $message, array $context = []): void
    {
        $this->write(TranscriptSection::LOG, [
            'level' => $level,
            'message' => $message,
        ], $context === [] ? null : $context);
    }

    public function writeWireInbound(string $frame, array $headers, int $inboundBatchId): void
    {
        $this->write(TranscriptSection::WIRE_INBOUND, [
            'inbound_batch_id' => $inboundBatchId,
            'bytes' => \strlen($frame),
        ], [
            'headers' => $headers,
            'body' => $this->safeDecodeFrame($frame),
        ]);
    }

    public function writeWireOutbound(string $frame, int $inboundBatchId, int $outboundSeq): void
    {
        $this->write(TranscriptSection::WIRE_OUTBOUND, [
            'inbound_batch_id' => $inboundBatchId,
            'outbound_seq' => $outboundSeq,
            'bytes' => \strlen($frame),
        ], [
            'body' => $this->safeDecodeFrame($frame),
        ]);
    }

    public function writeWireError(\Throwable $error): void
    {
        $this->write(TranscriptSection::WIRE_ERROR, [
            'class' => $error::class,
        ], [
            'message' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
        ]);
    }

    public function writeException(string $phase, array $attributes, \Throwable $exception): void
    {
        $this->write(TranscriptSection::EXCEPTION, ['phase' => $phase] + $attributes, [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'previous' => $exception->getPrevious()?->getMessage(),
        ]);
    }

    public function writeFatal(\Throwable $throwable): void
    {
        $this->write(TranscriptSection::FATAL, [
            'class' => $throwable::class,
        ], [
            'message' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ]);
    }

    /**
     * @param array<string, scalar|null|array> $errorRecord
     */
    public function writeFatalFromError(array $errorRecord): void
    {
        $this->write(TranscriptSection::FATAL, [
            'type' => (int) ($errorRecord['type'] ?? 0),
            'file' => (string) ($errorRecord['file'] ?? ''),
            'line' => (int) ($errorRecord['line'] ?? 0),
        ], [
            'message' => (string) ($errorRecord['message'] ?? ''),
        ]);
    }

    public function writeError(int $type, string $message, string $file, int $line): void
    {
        $this->write(TranscriptSection::ERROR, [
            'type' => $type,
            'file' => $file,
            'line' => $line,
        ], [
            'message' => $message,
        ]);
    }

    public function writeTestBoundary(TranscriptSection $boundary, array $attributes): void
    {
        if ($boundary !== TranscriptSection::TEST_START && $boundary !== TranscriptSection::TEST_END) {
            return;
        }
        $this->write($boundary, $attributes);
    }

    public function writeHistoryEvent(string $workflowId, string $runId, array $eventAttributes, string $attributesJson): void
    {
        $this->write(TranscriptSection::HISTORY, [
            'workflow_id' => $workflowId,
            'run_id' => $runId,
        ] + $eventAttributes, [
            'attrs' => $attributesJson,
        ]);
    }

    public function writeHistoryError(string $workflowId, \Throwable $error): void
    {
        $this->write(TranscriptSection::HISTORY_ERROR, [
            'workflow_id' => $workflowId,
            'class' => $error::class,
        ], [
            'message' => $error->getMessage(),
        ]);
    }

    public function writeMeta(string $event, array $attributes = []): void
    {
        $this->write(TranscriptSection::META, ['event' => $event] + $attributes);
    }

    public function flush(): void
    {
        if ($this->fileDescriptor === null) {
            return;
        }
        @\fflush($this->fileDescriptor);
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    private function doWrite(TranscriptSection $section, array $attributes, mixed $payload): void
    {
        if ($this->fileDescriptor === null) {
            return;
        }
        $this->rotateIfNeeded();

        $this->sequence++;
        $record = [
            'ts' => (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.uP'),
            'pid' => $this->processId,
            'seq' => $this->sequence,
            'section' => $section->value,
            'attributes' => (object) $attributes,
        ];
        if ($payload !== null) {
            $record['payload'] = $payload;
        }

        $encoded = \json_encode($record, self::JSON_FLAGS);
        if ($encoded === false) {
            $encoded = \json_encode([
                'ts' => $record['ts'],
                'pid' => $this->processId,
                'seq' => $this->sequence,
                'section' => $section->value,
                'attributes' => (object) $attributes,
                'payload' => ['error' => 'json_encode_failed', 'message' => \json_last_error_msg()],
            ], self::JSON_FLAGS);
        }
        if ($encoded === false) {
            $this->stderr->error('transcript-writer-internal-error: json fallback failed', [
                'message' => \json_last_error_msg(),
            ]);
            return;
        }
        $line = $encoded . "\n";

        if (!\flock($this->fileDescriptor, \LOCK_EX)) {
            $this->stderr->error('transcript-writer-internal-error: flock failed');
            return;
        }
        try {
            \fwrite($this->fileDescriptor, $line);
            \fflush($this->fileDescriptor);
        } finally {
            \flock($this->fileDescriptor, \LOCK_UN);
        }
    }

    private function rotateIfNeeded(): void
    {
        $stat = @\fstat($this->fileDescriptor);
        if ($stat === false) {
            return;
        }
        if ($stat['size'] < self::SIZE_CAP_BYTES) {
            return;
        }
        $this->rotationCounter++;
        $rotated = TranscriptPaths::rotatedFile($this->currentPath, $this->rotationCounter);
        try {
            $this->filesystem->rename($this->currentPath, $rotated, true);
        } catch (IOException $ioError) {
            $this->stderr->error('transcript-writer-internal-error: rotate rename failed', [
                'from' => $this->currentPath,
                'to' => $rotated,
                'message' => $ioError->getMessage(),
            ]);
            return;
        }
        $this->openFileDescriptor($this->currentPath);
        $this->doWrite(TranscriptSection::META, [
            'event' => 'writer_rotated',
            'from' => $rotated,
            'to' => $this->currentPath,
            'reason' => 'size_cap',
        ], null);
    }

    private function openFileDescriptor(string $path): void
    {
        try {
            $this->filesystem->mkdir(\dirname($path));
        } catch (IOException $ioError) {
            $this->stderr->error('transcript-writer-internal-error: mkdir failed', [
                'path' => $path,
                'message' => $ioError->getMessage(),
            ]);
        }
        $resource = @\fopen($path, 'ab');
        if ($resource === false) {
            $this->stderr->error('transcript-writer-internal-error: fopen failed', ['path' => $path]);
            if (\is_resource($this->fileDescriptor)) {
                @\fclose($this->fileDescriptor);
            }
            $this->fileDescriptor = null;
            return;
        }
        if (\is_resource($this->fileDescriptor)) {
            @\fclose($this->fileDescriptor);
        }
        $this->fileDescriptor = $resource;
    }

    private function safeDecodeFrame(string $frame): mixed
    {
        $trimmed = \ltrim($frame);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = \json_decode($frame, true);
            if ($decoded !== null || \json_last_error() === \JSON_ERROR_NONE) {
                return ['encoding' => 'json', 'value' => $decoded];
            }
        }

        $temporalFrame = WireFrameDecoder::decode($frame);
        if ($temporalFrame !== null) {
            return $temporalFrame;
        }

        return [
            'encoding' => 'raw',
            'preview_base64' => \base64_encode(\substr($frame, 0, 512)),
        ];
    }
}
