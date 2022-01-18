<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Worker\Transport\Command\CommandInterface;

final class CompleteWorkflowHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?CommandInterface
    {
        if ($command->getFailure() !== null) {
            throw $command->getFailure();
        }

        return null;
        // @TODO: somehow figure out runId
        // return new Request('DestroyWorkflow', ['runId' => $this->runId]);
    }

    public function supports(CommandInterface $command): bool
    {
        return $command instanceof CompleteWorkflow;
    }
}
