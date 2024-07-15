<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Carbon\Carbon;
use DateTimeImmutable;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;

final class NewTimerHandler implements CommandHandlerInterface, AffectsServerStateHandler
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        return new SuccessResponse(EncodedValues::empty(), $command->getID(), new TickInfo(new DateTimeImmutable()));
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
