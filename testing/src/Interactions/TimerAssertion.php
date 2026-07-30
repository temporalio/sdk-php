<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Assert;

final class TimerAssertion
{
    /**
     * @param list<RecordedCall> $calls
     */
    public function __construct(
        private readonly array $calls,
    ) {}

    public function assertStarted(\DateInterval|int|string $duration): void
    {
        $expectedMs = self::toMilliseconds($duration);

        $matching = \array_filter($this->calls, static fn(RecordedCall $call): bool => $call->durationMs === $expectedMs);

        Assert::assertNotEmpty(
            $matching,
            \sprintf('No timer was started with duration %d ms', $expectedMs),
        );
    }

    public function assertStartedTimes(int $times): void
    {
        Assert::assertCount($times, $this->calls, 'Timer start count mismatch');
    }

    private static function toMilliseconds(\DateInterval|int|string $duration): int
    {
        if (\is_int($duration)) {
            return $duration;
        }

        return (int) CarbonInterval::make($duration)?->totalMilliseconds;
    }
}
