<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Assertion;

use Temporal\Worker\Transport\Command\CommandInterface;

/**
 * @internal
 */
interface AssertionInterface
{
    public function matches(CommandInterface $command): bool;

    public function assert(CommandInterface $command): void;
}
