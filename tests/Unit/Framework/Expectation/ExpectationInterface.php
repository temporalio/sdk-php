<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Expectation;

use PHPUnit\Framework\ExpectationFailedException;
use Temporal\Worker\Transport\Command\CommandInterface;

/**
 * @internal
 */
interface ExpectationInterface
{
    public function matches(CommandInterface $command): bool;

    public function run(CommandInterface $command): CommandInterface;

    /**
     * @throws ExpectationFailedException
     */
    public function fail(): void;
}
