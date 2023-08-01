<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use RoadRunner\Temporal\DTO\V1\ReplayRequest;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;

/** Replays a workflow given its history. Useful for backwards compatibility testing. */
final class WorkflowReplayer
{
    private RPC $rpc;

    public function __construct()
    {
        $this->rpc = new RPC(Relay::create(Environment::fromGlobals()->getRPCAddress()), new ProtobufCodec());
    }

    /**
     * Replays workflow from a resource that contains a json serialized history.
     */
    public function replayWorkflowExecutionFromFile(string $filename, string $workflowClass): void
    {
        $workflowExecutionHistory = WorkflowExecutionHistory::fromFile($filename);
        $this->replayWorkflowExecution($workflowExecutionHistory, $workflowClass);
    }

    public function replayFromServer(
        \Temporal\Workflow\WorkflowExecution $execution,
        string $workflowType,
    ): void {
        $request = $this->buildRequest($workflowType, $execution);
        $result = $this->rpc->call('temporal.ReplayWorkflow', $request);
        \trap(...[$workflowType => $result]);
    }

    public function downloadHistory(
        \Temporal\Workflow\WorkflowExecution $execution,
        string $workflowType,
        string $savPath,
    ): void {
        $request = $this->buildRequest($workflowType, $execution, $savPath);
        $result = $this->rpc->call('temporal.DownloadWorkflowHistory', $request);
        \trap(...[$workflowType => $result]);
    }

    public function replayFromJSONPB(
        string $workflowType,
        string $path,
    ): void {
        $request = $this->buildRequest($workflowType, filePath: $path);
        $result = $this->rpc->call('temporal.ReplayFromJSONPB', $request);
        \trap(...[$workflowType => $result]);
    }

    /**
     * @param class-string $workflowClass
     */
    private function replayWorkflowExecution(
        WorkflowExecutionHistory $workflowExecutionHistory,
        string $workflowClass,
    ): void {
        $result = $this->rpc->call('temporal.ReplayWorkflow', new ReplayRequest([
            'workflow_execution' => new WorkflowExecution([
                'run_id' => $run->getExecution()->getRunID(),
                'workflow_id' => $run->getExecution()->getID(),
            ]),
            'workflow_type' => new WorkflowType([
                'name' => 'NonDetermenisticWorkflow',
            ]),
        ]));
    }

    private function buildRequest(
        string $workflowType,
        ?\Temporal\Workflow\WorkflowExecution $execution = null,
        ?string $filePath = null,
    ): ReplayRequest {
        $request = (new ReplayRequest())->setWorkflowType((new WorkflowType())->setName($workflowType));

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
