<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Carbon\Carbon;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;

final class NewTimerHandler implements CommandHandlerInterface, AffectsServerStateHandler
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        return new SuccessResponse(null, $command->getID());
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof NewTimer;
    }

    public function updateState(CommandInterface $command, Carbon $state): void
    {
        $state->addSeconds($command->getOptions()['ms']);
    }
}
