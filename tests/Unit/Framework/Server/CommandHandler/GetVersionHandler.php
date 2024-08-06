<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use DateTimeImmutable;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow;

final class GetVersionHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        return new SuccessResponse(EncodedValues::fromValues([Workflow::DEFAULT_VERSION]), $command->getID(), new TickInfo(new DateTimeImmutable()));
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof GetVersion;
    }
}
