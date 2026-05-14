<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;

final class TranscriptAdapter implements LoggerInterface
{
    use LoggerTrait;

    private readonly LoggerInterface $stderr;

    public function __construct(
        private readonly TranscriptWriter $writer,
        ?LoggerInterface $stderr = null,
    ) {
        $this->stderr = $stderr ?? new NullLogger();
    }

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
