<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class FanoutLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<LoggerInterface> */
    private readonly array $sinks;

    public function __construct(
        private readonly LoggerInterface $stderr,
        LoggerInterface ...$sinks,
    ) {
        $this->sinks = $sinks;
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        foreach ($this->sinks as $sink) {
            try {
                $sink->log($level, $message, $context);
            } catch (\Throwable $error) {
                $this->stderr->error('fanout-logger-error', [
                    'sink' => $sink::class,
                    'message' => $error->getMessage(),
                ]);
            }
        }
    }
}
