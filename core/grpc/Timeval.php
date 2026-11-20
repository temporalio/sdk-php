<?php

declare(strict_types=1);

namespace Grpc;

class Timeval
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;
    private const NANOSECONDS_PER_MICROSECOND = 1000;

    public function __construct(private readonly int $microseconds) {}

    public static function now(): self
    {
        return new self(\intdiv(\hrtime(true), self::NANOSECONDS_PER_MICROSECOND));
    }

    public static function infFuture(): self
    {
        return new self(\PHP_INT_MAX);
    }

    public static function compare(self $a_timeval, self $b_timeval): int
    {
        return $a_timeval->microseconds <=> $b_timeval->microseconds;
    }

    public function add(self $timeval): self
    {
        $sum = $this->microseconds + $timeval->microseconds;
        if ($this->microseconds === \PHP_INT_MAX || $timeval->microseconds === \PHP_INT_MAX || !\is_int($sum)) {
            return self::infFuture();
        }

        return new self($sum);
    }

    public function sleepUntil(): void
    {
        $left = $this->microseconds - self::now()->microseconds;
        if ($left > 0) {
            \usleep($left);
        }
    }

    /**
     * @internal
     */
    public function millisecondsLeft(): int
    {
        $left = $this->microseconds - self::now()->microseconds;

        return $left > 0 ? \intdiv($left + self::MICROSECONDS_PER_MILLISECOND - 1, self::MICROSECONDS_PER_MILLISECOND) : 0;
    }
}
