<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Worker\Transport\Command\CommandInterface;

interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface;

    public function supports(CommandInterface $command): bool;
}
