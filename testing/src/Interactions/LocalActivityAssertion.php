<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use PHPUnit\Framework\Assert;

final class LocalActivityAssertion
{
    /**
     * @param list<RecordedCall> $calls
     */
    public function __construct(
        private readonly string $type,
        private readonly array $calls,
    ) {}

    public function assertCalledTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->calls,
            \sprintf('Local activity "%s" call count mismatch', $this->type),
        );
    }

    public function assertCalledOnce(): void
    {
        $this->assertCalledTimes(1);
    }

    public function assertNeverCalled(): void
    {
        $this->assertCalledTimes(0);
    }
}
