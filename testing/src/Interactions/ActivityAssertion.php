<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use PHPUnit\Framework\Assert;
use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\PayloadComparator;

final class ActivityAssertion
{
    private ?Payloads $expectedInput = null;

    /**
     * @param list<RecordedCall> $calls
     */
    public function __construct(
        private readonly string $type,
        private readonly array $calls,
        private readonly DataConverterInterface $converter,
    ) {}

    public function withInput(mixed ...$args): self
    {
        $this->expectedInput = EncodedValues::fromValues(\array_values($args), $this->converter)->toPayloads();

        return $this;
    }

    public function assertCalledTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->matchingCalls(),
            \sprintf('Activity "%s" call count mismatch', $this->type),
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

    /**
     * @return list<RecordedCall>
     */
    private function matchingCalls(): array
    {
        if ($this->expectedInput === null) {
            return $this->calls;
        }

        $result = [];
        foreach ($this->calls as $call) {
            if (PayloadComparator::equals($call->input, $this->expectedInput)) {
                $result[] = $call;
            }
        }

        return $result;
    }
}
