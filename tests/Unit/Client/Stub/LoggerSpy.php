<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Stub;

use Psr\Log\AbstractLogger;

/**
 * Collects the log records with their level, so a test can assert on all of them.
 *
 * @internal
 */
final class LoggerSpy extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array, kind: string}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $message = (string) $message;

        $this->records[] = [
            'level' => (string) $level,
            'message' => $message,
            'context' => $context,
            'kind' => \str_contains($message, ' memo ') ? 'memo' : 'payloads',
        ];
    }
}
