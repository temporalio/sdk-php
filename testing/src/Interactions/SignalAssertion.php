<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use PHPUnit\Framework\Assert;

final class SignalAssertion
{
    /**
     * @param list<RecordedCall> $calls
     */
    public function __construct(
        private readonly string $type,
        private readonly array $calls,
    ) {}

    public function assertSentTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->calls,
            \sprintf('External signal "%s" sent count mismatch', $this->type),
        );
    }

    public function assertSentOnce(): void
    {
        $this->assertSentTimes(1);
    }

    public function assertNeverSent(): void
    {
        $this->assertSentTimes(0);
    }
}
