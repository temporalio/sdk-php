<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class TranscriptAdapter implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly TranscriptWriter $writer,
        private readonly LoggerInterface $stderr,
    ) {}

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        try {
            $this->writer->writeLog((string) $level, (string) $message, $context);
        } catch (\Throwable $error) {
            $this->stderr->error('transcript-adapter-error', [
                'message' => $error->getMessage(),
            ]);
        }
    }
}
