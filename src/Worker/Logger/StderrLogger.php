<?php

declare(strict_types=1);

namespace Temporal\Worker\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class StderrLogger implements LoggerInterface
{
    use LoggerTrait;

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        \fwrite(\STDERR, \sprintf(
            "[%s] %s: %s%s\n",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . (string) \json_encode($context, \JSON_INVALID_UTF8_IGNORE),
        ));
    }
}
