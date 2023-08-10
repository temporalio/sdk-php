<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Google\Protobuf\Internal\Message;
use RoadRunner\Temporal\DTO\V1\ReplayRequest;
use RoadRunner\Temporal\DTO\V1\ReplayResponse;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use SplFileInfo;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Testing\Replay\Exception\InternalServerException;
use Temporal\Testing\Replay\Exception\InvalidArgumentException;
use Temporal\Testing\Replay\Exception\NonDeterministicWorkflowException;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Testing\Replay\Exception\RPCException;

/**
 * Replays a workflow given its history. Useful for backwards compatibility testing.
 *
 * @link https://docs.temporal.io/dev-guide/php/testing#replay
 * @since RoadRunner 2023.3
 */
final class WorkflowReplayer
{
    private RPC $rpc;

    public function __construct()
    {
        $this->rpc = new RPC(Relay::create(Environment::fromGlobals()->getRPCAddress()), new ProtobufCodec());
    }

    /**
     * Replays a workflow from {@see History}
     *
     * @throws ReplayerException
     */
    public function replayHistory(History $history): void
    {
        /** @var HistoryEvent|null $firstEvent */
        $firstEvent = $history->getEvents()[0] ?? null;
        $workflowType = $firstEvent?->getWorkflowExecutionStartedEventAttributes()?->getWorkflowType()?->getName()
            ?? throw new \LogicException('History is empty or broken.');

        $request = (new \RoadRunner\Temporal\DTO\V1\History())
            ->setWorkflowType((new WorkflowType())->setName($workflowType))
            ->setHistory($history);

        $this->sendRequest('temporal.ReplayWorkflowHistory', $request);
    }

    /**
     * Replays a workflow from history that will be fetched from Temporal server.
     *
     * @throws ReplayerException
     */
    public function replayFromServer(
        string $workflowType,
        \Temporal\Workflow\WorkflowExecution $execution,
    ): void {
        $request = $this->buildRequest($workflowType, $execution);
        $this->sendRequest('temporal.ReplayWorkflow', $request);
    }

    /**
     * Downloads workflow history from Temporal server and saves it to a file.
     *
     * @param non-empty-string $workflowType
     * @param non-empty-string $savePath
     *
     * @throws ReplayerException
     */
    public function downloadHistory(
        string $workflowType,
        \Temporal\Workflow\WorkflowExecution $execution,
        string $savePath,
    ): void {
        $request = $this->buildRequest($workflowType, $execution, $savePath);
        $this->sendRequest('temporal.DownloadWorkflowHistory', $request);
    }

    /**
     * Replays workflow from a json serialized history file.
     * You can load a json serialized history file using {@see downloadHistory()} or via Temporal UI.
     *
     * @param non-empty-string $workflowType
     * @param non-empty-string|SplFileInfo $path
     * @param int<0, max> $lastEventId The last event ID to replay from. If not specified, the whole history
     *        will be replayed.
     *
     * @throws ReplayerException
     */
    public function replayFromJSON(
        string $workflowType,
        string|SplFileInfo $path,
        int $lastEventId = 0,
    ): void {
        $request = $this->buildRequest(
            workflowType: $workflowType,
            filePath: $path instanceof SplFileInfo ? $path->getPathname() : $path,
            lastEventId: $lastEventId,
        );
        $this->sendRequest('temporal.ReplayFromJSON', $request);
    }

    private function sendRequest(string $command, Message $request): ReplayResponse
    {
        $wfType = (string)$request->getWorkflowType()?->getName();
        try {
            /** @var string $result */
            $result = $this->rpc->call($command, $request);
        } catch (\Throwable $e) {
            throw new RPCException(
                $wfType, $e->getMessage(), (int)$e->getCode(), $e
            );
        }

        $message = new ReplayResponse();
        $message->mergeFromString($result);

        $status = $message->getStatus();
        \assert($status !== null);

        if ($status->getCode() === 0) {
            return $message;
        }

        throw match ($status->getCode()) {
            StatusCode::INVALID_ARGUMENT => new InvalidArgumentException(
                $wfType, $status->getMessage(), $status->getCode(),
            ),
            StatusCode::INTERNAL => new InternalServerException($wfType, $status->getMessage(), $status->getCode()),
            StatusCode::FAILED_PRECONDITION => new NonDeterministicWorkflowException(
                $wfType, $status->getMessage(), $status->getCode(),
            ),
            default => new ReplayerException($wfType, $status->getMessage(), $status->getCode()),
        };
    }

    private function buildRequest(
        string $workflowType,
        ?\Temporal\Workflow\WorkflowExecution $execution = null,
        ?string $filePath = null,
        int $lastEventId = 0,
    ): ReplayRequest {
        $request = (new ReplayRequest())
            ->setWorkflowType((new WorkflowType())->setName($workflowType))
            ->setLastEventId($lastEventId);

        if ($execution !== null) {
            $request->setWorkflowExecution((new WorkflowExecution())
                ->setWorkflowId($execution->getID())
                ->setRunId($execution->getRunID() ?? throw new \LogicException('Run ID is required.'))
            );
        }

        if ($filePath !== null) {
            $request->setSavePath($filePath);
        }


        return $request;
    }
}
