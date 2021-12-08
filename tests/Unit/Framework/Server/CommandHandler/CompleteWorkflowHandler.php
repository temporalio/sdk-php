<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Request;

final class CompleteWorkflowHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        if ($command->getFailure() !== null) {
            throw $command->getFailure();
        }

        return new Request('DestroyWorkflow', ['runId' => '744fba1a-a9d9-4447-9aad-98d274dcfc27']);
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof CompleteWorkflow;
    }
}
