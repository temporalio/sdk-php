<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponse;

final class FailureResponseHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        if ($command->getFailure() !== null) {
            throw $command->getFailure();
        }

        return null;
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof FailureResponse;
    }
}
