<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use PHPUnit\Framework\Assert;

final class ChildWorkflowAssertion
{
    /**
     * @param list<RecordedCall> $calls
     */
    public function __construct(
        private readonly string $type,
        private readonly array $calls,
    ) {}

    public function assertStartedTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->calls,
            \sprintf('Child workflow "%s" start count mismatch', $this->type),
        );
    }

    public function assertStartedOnce(): void
    {
        $this->assertStartedTimes(1);
    }

    public function assertNeverStarted(): void
    {
        $this->assertStartedTimes(0);
    }
}
