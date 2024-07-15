<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Worker\Transport\Command\Client\Response;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Workflow;

final class GetVersionHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        return Response::createSuccess(EncodedValues::fromValues([Workflow::DEFAULT_VERSION]), $command->getID());
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof GetVersion;
    }
}
