<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\Query\V1\WorkflowQueryResult;
use Temporal\Worker\Transport\Command\Command;

final class WorkflowTaskResult
{
    /** @var Command[] $commands */
    private array $commands;
    /** @var WorkflowQueryResult[] $queryResults */
    private array $queryResults;
    private bool $hasFinalCommand;
    private bool $forceWorkflowTask;

    public function __construct(array $commands, array $queryResults, bool $hasFinalCommand, bool $forceWorkflowTask) {
        $this->commands = $commands;
        $this->queryResults = $queryResults;
        $this->hasFinalCommand = $hasFinalCommand;
        $this->forceWorkflowTask = $forceWorkflowTask;
    }

    /**
     * @return Command[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @return WorkflowQueryResult[]
     */
    public function getQueryResults(): array
    {
        return $this->queryResults;
    }

    /**
     * Does this result contain a workflow completion command
     */
    public function hasFinalCommand(): bool
    {
        return $this->hasFinalCommand;
    }

    public function isForceWorkflowTask(): bool
    {
        return $this->forceWorkflowTask;
    }
}
