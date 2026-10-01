<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\History\V1\History;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Testing\Replay\Exception\InvalidArgumentException;
use Temporal\Testing\Replay\Exception\NonDeterministicWorkflowException;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Worker\Core\ReplayFailedException;
use Temporal\Workflow\WorkflowExecution;

final class CoreWorkflowReplayer
{
    private const REPLAY_WORKFLOW_ID = 'replay';

    private readonly HistoryJsonCodec $codec;

    public function __construct(
        private readonly CoreWorkerFactory $factory,
        private readonly ?WorkflowClientInterface $client = null,
    ) {
        $this->codec = new HistoryJsonCodec();
    }

    public function replayHistory(History $history): void
    {
        $events = $history->getEvents();
        $workflowType = \count($events) === 0
            ? null
            : $events[0]->getWorkflowExecutionStartedEventAttributes()?->getWorkflowType()?->getName();
        if ($workflowType === null) {
            throw new \LogicException('History is empty or broken.');
        }

        $this->replay($workflowType, $history, self::REPLAY_WORKFLOW_ID);
    }

    public function replayFromServer(string $workflowType, WorkflowExecution $execution): void
    {
        $this->replay($workflowType, $this->fetchHistory($execution), $execution->getID());
    }

    public function downloadHistory(string $workflowType, WorkflowExecution $execution, string $savePath): void
    {
        \file_put_contents($savePath, $this->codec->encode($this->fetchHistory($execution)));
    }

    public function replayFromJSON(string $workflowType, string|\SplFileInfo $path, int $lastEventId = 0): void
    {
        $path = $path instanceof \SplFileInfo ? $path->getPathname() : $path;
        if (!\is_file($path)) {
            throw new InvalidArgumentException($workflowType, \sprintf('History file "%s" does not exist.', $path), StatusCode::INVALID_ARGUMENT);
        }

        $json = \file_get_contents($path);
        if ($json === false) {
            throw new InvalidArgumentException($workflowType, \sprintf('History file "%s" cannot be read.', $path), StatusCode::INVALID_ARGUMENT);
        }

        $this->replay($workflowType, $this->codec->decode($json, $lastEventId), self::REPLAY_WORKFLOW_ID);
    }

    private function replay(string $workflowType, History $history, string $workflowId): void
    {
        try {
            $this->factory->replay($history, $workflowId);
        } catch (ReplayFailedException $e) {
            throw $e->nonDeterministic
                ? new NonDeterministicWorkflowException($workflowType, $e->getMessage(), StatusCode::FAILED_PRECONDITION, $e)
                : new ReplayerException($workflowType, $e->getMessage(), StatusCode::INTERNAL, $e);
        }
    }

    private function fetchHistory(WorkflowExecution $execution): History
    {
        $client = $this->client ?? throw new \LogicException('A workflow client is required to fetch a history.');

        return $client->getWorkflowHistory($execution)->getHistory();
    }
}
