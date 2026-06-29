<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class TranscriptAdapter implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly TranscriptWriter $writer,
    ) {}

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        try {
            $this->writer->writeLog((string) $level, (string) $message, $context);
        } catch (\Throwable $error) {
            \fwrite(\STDERR, '[transcript-adapter-error] ' . $error->getMessage() . "\n");
        }
    }
}
