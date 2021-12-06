<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Tests\Unit\Framework\Requests\InvokeActivity;
use Temporal\Worker\Transport\Command\CommandInterface;

final class ExecuteActivityHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        return new InvokeActivity($command->getOptions()['name'], $command->getPayloads());
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof ExecuteActivity;
    }
}
