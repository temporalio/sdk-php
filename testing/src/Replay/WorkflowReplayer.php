<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use RoadRunner\Temporal\DTO\V1\ReplayRequest;
use RoadRunner\Temporal\DTO\V1\ReplayResponse;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use SplFileInfo;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
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
     * Replays workflow from a history that will be loaded from Temporal server.
     */
    public function replayFromServer(
        \Temporal\Workflow\WorkflowExecution $execution,
        string $workflowType,
    ): void {
        $request = $this->buildRequest($workflowType, $execution);
        $this->sendRequest('temporal.ReplayWorkflow', $request);
    }

    public function downloadHistory(
        \Temporal\Workflow\WorkflowExecution $execution,
        string $workflowType,
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
     */
    public function replayFromJSON(
        string $workflowType,
        string|SplFileInfo $path,
    ): void {
        $request = $this->buildRequest(
            workflowType: $workflowType,
            filePath: $path instanceof SplFileInfo ? $path->getPathname() : $path,
        );
        $this->sendRequest('temporal.ReplayFromJSON', $request);
    }

    private function sendRequest(string $commad, ReplayRequest $request): ReplayResponse
    {
        $wfType = (string)$request->getWorkflowType()?->getName();
        try {
            /** @var string $result */
            $result = $this->rpc->call($commad, $request);
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
            13 => new NonDeterministicWorkflowException($wfType, $status->getMessage(), $status->getCode()),
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
