<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework;

use Temporal\Worker\Transport\Command\Command;

/**
 * @internal
 */
final class CommandBatchMock
{
    /** @var Command[] */
    public array $commands;
    public array $context;

    public function __construct(array $commands, array $context)
    {
        $this->commands = $commands;
        $this->context = $context;
    }

    public static function empty(array $context): self
    {
        return new self([], $context);
    }
}
