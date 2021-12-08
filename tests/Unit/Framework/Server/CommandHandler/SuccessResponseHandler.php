<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;

final class SuccessResponseHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        // just do nothing
        return null;
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof SuccessResponse;
    }
}
