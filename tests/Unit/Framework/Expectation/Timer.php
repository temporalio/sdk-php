<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Expectation;

use DateTimeImmutable;
use PHPUnit\Framework\ExpectationFailedException;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * @internal
 */
final class Timer implements ExpectationInterface
{
    private int $seconds;

    /**
     * @param null|string $summary Timer summary to check. NULL means no check.
     */
    public function __construct(int $seconds, private readonly ?string $summary = null)
    {
        $this->seconds = $seconds;
    }

    public function matches(CommandInterface $command): bool
    {
        if (!$command instanceof NewTimer || $command->getOptions()['ms'] / 1000 !== $this->seconds) {
            return false;
        }

        return $this->summary === null || ($command->getOptions()['summary'] ?? null) === $this->summary;
    }

    public function run(CommandInterface $command): CommandInterface
    {
        return new SuccessResponse(EncodedValues::empty(), $command->getID(), new TickInfo(new DateTimeImmutable()));
    }

    public function fail(): void
    {
        throw new ExpectationFailedException(
            $this->summary === null
                ? "Expected timer for $this->seconds seconds."
                : "Expected timer for $this->seconds seconds with summary `$this->summary`.",
        );
    }
}
