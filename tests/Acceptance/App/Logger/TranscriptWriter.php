<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

/**
 * Persistent-FD, lock-serialized, append-only transcript writer.
 *
 * Every public write method catches every internal Throwable and emits to STDERR only —
 * transcript writes must never throw upward, because they are invoked from shutdown
 * handlers, destructors, and exception interceptors where re-entry would mask the
 * original failure.
 */
final class TranscriptWriter
{
    private const SIZE_CAP_BYTES = 50 * 1024 * 1024;

    /** @var resource|null */
    private $fileDescriptor;

    private string $currentPath;

    private int $sequence = 0;

    private int $rotationCounter = 0;

    private readonly int $processId;

    private readonly int $workerStartEpochMs;

    private bool $inWrite = false;

    /**
     * @param non-empty-string $path Initial transcript file path
     */
    public function __construct(string $path)
    {
        $this->processId = \getmypid() ?: 0;
        $this->workerStartEpochMs = (int) (\microtime(true) * 1000);
        $this->currentPath = $path;
        $this->openFileDescriptor($path);
        $this->writeMeta('writer_initialized', [
            'path' => $path,
            'worker_start_epoch_ms' => $this->workerStartEpochMs,
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
            \fwrite(\STDERR, '[transcript-writer-internal-error] ' . $e::class . ': ' . $e->getMessage() . "\n");
        } finally {
            $this->inWrite = false;
        }
    }

    public function writeLog(string $level, string $message, array $context = []): void
    {
        $this->write(TranscriptSection::LOG, [
            'level' => $level,
            'message' => $this->oneLine($message),
        ], $context === [] ? null : $context);
    }

    public function writeWireInbound(string $frame, array $headers, int $frameId): void
    {
        $this->write(TranscriptSection::WIRE_INBOUND, [
            'frame_id' => $frameId,
            'bytes' => \strlen($frame),
        ], [
            'headers' => $headers,
            'body' => $this->safeDecodeFrame($frame),
        ]);
    }

    public function writeWireOutbound(string $frame, int $frameId): void
    {
        $this->write(TranscriptSection::WIRE_OUTBOUND, [
            'frame_id' => $frameId,
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
        $timestamp = (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.uP');
        $line = $timestamp . ' '
            . $this->processId . ' '
            . $this->sequence . ' '
            . '[' . $section->value . '] '
            . $this->formatAttributes($attributes);

        if ($payload !== null) {
            $line .= ' payload=' . $this->encodePayload($payload);
        }

        $line .= "\n";

        if (!\flock($this->fileDescriptor, \LOCK_EX)) {
            \fwrite(\STDERR, "[transcript-writer-internal-error] flock failed\n");
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
        $rotated = $this->currentPath . '.' . $this->rotationCounter;
        @\rename($this->currentPath, $rotated);
        $this->openFileDescriptor($this->currentPath);
        $this->writeMeta('writer_rotated', [
            'from' => $rotated,
            'to' => $this->currentPath,
            'reason' => 'size_cap',
        ]);
    }

    private function openFileDescriptor(string $path): void
    {
        $directory = \dirname($path);
        if (!\is_dir($directory)) {
            @\mkdir($directory, 0777, true);
        }
        $resource = @\fopen($path, 'ab');
        if ($resource === false) {
            \fwrite(\STDERR, "[transcript-writer-internal-error] fopen failed for {$path}\n");
            $this->fileDescriptor = null;
            return;
        }
        $this->fileDescriptor = $resource;
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    private function formatAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = $key . '=' . $this->encodeAttributeValue($value);
        }
        return \implode(' ', $parts);
    }

    private function encodeAttributeValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        $stringValue = (string) $value;
        if (\preg_match('/[\s"]/', $stringValue) === 1) {
            return '"' . \str_replace(['\\', '"'], ['\\\\', '\\"'], $stringValue) . '"';
        }
        return $stringValue;
    }

    private function encodePayload(mixed $payload): string
    {
        $encoded = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return '"<json_encode_failed>"';
        }
        return $encoded;
    }

    private function oneLine(string $value): string
    {
        return \strtr($value, ["\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
    }

    private function safeDecodeFrame(string $frame): mixed
    {
        $trimmed = \ltrim($frame);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return [
                'encoding' => 'raw',
                'preview_base64' => \base64_encode(\substr($frame, 0, 512)),
            ];
        }
        $decoded = \json_decode($frame, true);
        if ($decoded === null && \json_last_error() !== \JSON_ERROR_NONE) {
            return [
                'encoding' => 'raw',
                'preview_base64' => \base64_encode(\substr($frame, 0, 512)),
            ];
        }
        return ['encoding' => 'json', 'value' => $decoded];
    }
}
