<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Carbon\Carbon;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Worker\Transport\Command\Client\Response;
use Temporal\Worker\Transport\Command\CommandInterface;

final class NewTimerHandler implements CommandHandlerInterface, AffectsServerStateHandler
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        return Response::createSuccess(EncodedValues::empty(), $command->getID());
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
