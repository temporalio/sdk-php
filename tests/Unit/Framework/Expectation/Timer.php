<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Expectation;

use PHPUnit\Framework\ExpectationFailedException;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;

/**
 * @internal
 */
final class Timer implements ExpectationInterface
{
    private int $seconds;

    public function __construct(int $seconds)
    {
        $this->seconds = $seconds;
    }

    public function matches(CommandInterface $command): bool
    {
        return $command instanceof NewTimer && $command->getOptions()['ms'] / 1000 === $this->seconds;
    }

    public function run(CommandInterface $command): CommandInterface
    {
        return new SuccessResponse(null, $command->getID());
    }

    public function fail(): void
    {
        throw new ExpectationFailedException("Expected timer for $this->seconds seconds.");
    }
}
