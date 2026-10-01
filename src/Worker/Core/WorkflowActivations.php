<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Workflow_activation\DoUpdate;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Coresdk\Workflow_activation\QueryWorkflow;
use Coresdk\Workflow_activation\WorkflowActivation;
use Coresdk\Workflow_commands\QueryResult;
use Coresdk\Workflow_commands\QuerySuccess;
use Coresdk\Workflow_commands\WorkflowCommand;
use Coresdk\Workflow_completion\Failure as CompletionFailure;
use Coresdk\Workflow_completion\Success as CompletionSuccess;
use Coresdk\Workflow_completion\WorkflowActivationCompletion;
use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\WorkerFactory;

final class WorkflowActivations
{
    /** @var array<string, RunState> */
    private array $runs = [];

    private readonly \DateTimeZone $timeZone;
    private readonly PayloadMapper $payloads;
    private readonly InfoFactory $info;
    private readonly ResolutionMapper $resolutions;
    private readonly CommandTranslator $translator;

    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * @param \Closure(list<CommandInterface>, array): list<CommandInterface> $dispatch
     * @param array<string, int> $versioningBehaviors
     */
    public function __construct(
        DataConverterInterface $converter,
        private readonly \Closure $dispatch,
        private readonly string $namespace,
        private readonly string $taskQueue,
        private readonly array $versioningBehaviors,
        bool $failWorkflowOnPanic = false,
    ) {
        $this->timeZone = new \DateTimeZone(\date_default_timezone_get());
        $this->payloads = new PayloadMapper($converter);
        $this->info = new InfoFactory($this->payloads);
        $this->resolutions = new ResolutionMapper($this->payloads, $namespace);
        $this->translator = new CommandTranslator($this->payloads, $this->resolutions, $namespace, $taskQueue, $failWorkflowOnPanic);
        $this->headers = [WorkerFactory::HEADER_TASK_QUEUE => $taskQueue];
    }

    public function handle(string $bytes): string
    {
        $activation = new WorkflowActivation();
        $activation->mergeFromString($bytes);
        $completion = new WorkflowActivationCompletion(['run_id' => $activation->getRunId()]);

        try {
            $commands = $this->process($activation);
            $completion->setSuccessful(new CompletionSuccess([
                'commands' => $commands,
                'versioning_behavior' => $this->runs[$activation->getRunId()]->versioningBehavior ?? 0,
            ]));
        } catch (\Throwable $e) {
            $completion->setFailed(new CompletionFailure([
                'failure' => $this->payloads->failure($e),
            ]));
        }

        return $completion->serializeToString();
    }

    /**
     * @return list<WorkflowCommand>
     */
    private function process(WorkflowActivation $activation): array
    {
        $runId = $activation->getRunId();
        $tick = new TickInfo(
            time: ProtoTime::dateTime($activation->getTimestamp(), $this->timeZone),
            historyLength: $activation->getHistoryLength(),
            historySize: (int) $activation->getHistorySizeBytes(),
            continueAsNewSuggested: $activation->getContinueAsNewSuggested(),
            isReplaying: $activation->getIsReplaying(),
        );
        $run = $this->runs[$runId] ??= new RunState($runId);

        $messages = [];
        $queries = [];
        $commands = [];
        foreach ($activation->getJobs() as $job) {
            switch ($job->getVariant()) {
                case 'initialize_workflow':
                    $run->versioningBehavior = $this->versioningBehaviors[$job->getInitializeWorkflow()->getWorkflowType()] ?? 0;
                    $messages[] = $this->startWorkflow($job->getInitializeWorkflow(), $runId, $tick);
                    break;
                case 'fire_timer':
                    $seq = $job->getFireTimer()->getSeq();
                    if ($run->localActivities->isBackoffTimer($seq)) {
                        $commands[] = $run->localActivities->retry($seq, $run->rebind($seq, RunState::LOCAL_ACTIVITY));
                        break;
                    }
                    $messages[] = new SuccessResponse(null, $run->release($seq), $tick);
                    break;
                case 'resolve_activity':
                    $resolve = $job->getResolveActivity();
                    $seq = $resolve->getSeq();
                    if ($resolve->getResult()->getStatus() === 'backoff') {
                        $commands[] = $run->localActivities->backoff($seq, $run->rebind($seq, RunState::TIMER), $resolve->getResult()->getBackoff());
                        break;
                    }
                    $messages[] = $this->resolutions->activity($run, $seq, $resolve->getResult(), $tick);
                    break;
                case 'signal_workflow':
                    $signal = $job->getSignalWorkflow();
                    $messages[] = new ServerRequest(
                        name: 'InvokeSignal',
                        info: $tick,
                        options: ['runId' => $runId, 'name' => $signal->getSignalName()],
                        payloads: $this->payloads->values($signal->getInput()),
                        id: $runId,
                        header: $this->payloads->header($signal->getHeaders()),
                    );
                    break;
                case 'query_workflow':
                    $queries[] = $job->getQueryWorkflow();
                    break;
                case 'cancel_workflow':
                    $messages[] = new ServerRequest('CancelWorkflow', $tick, ['runId' => $runId], id: $runId);
                    break;
                case 'do_update':
                    $messages[] = $this->update($run, $job->getDoUpdate(), $tick);
                    break;
                case 'notify_has_patch':
                    $run->patches->notify($job->getNotifyHasPatch()->getPatchId());
                    break;
                case 'resolve_child_workflow_execution_start':
                    \array_push($messages, ...$this->resolutions->childStarted($run, $job->getResolveChildWorkflowExecutionStart(), $tick));
                    break;
                case 'resolve_child_workflow_execution':
                    $messages[] = $this->resolutions->childResult($run, $job->getResolveChildWorkflowExecution(), $tick);
                    break;
                case 'resolve_signal_external_workflow':
                    $resolve = $job->getResolveSignalExternalWorkflow();
                    $messages[] = $this->resolutions->external($run->release($resolve->getSeq()), $resolve->getFailure(), $tick);
                    break;
                case 'resolve_request_cancel_external_workflow':
                    $resolve = $job->getResolveRequestCancelExternalWorkflow();
                    $messages[] = $this->resolutions->external($run->release($resolve->getSeq()), $resolve->getFailure(), $tick);
                    break;
                case 'remove_from_cache':
                    unset($this->runs[$runId]);
                    ($this->dispatch)([new ServerRequest('DestroyWorkflow', $tick, ['runId' => $runId], id: $runId)], $this->headers);
                    return [];
                case 'update_random_seed':
                    break;
                default:
                    throw new \LogicException(\sprintf('Unsupported activation job "%s"', $job->getVariant()));
            }
        }

        $this->exchange($run, $messages, $tick, $commands);

        if ($queries !== []) {
            $queryTick = new TickInfo($tick->time, $tick->historyLength, $tick->historySize, $tick->continueAsNewSuggested);
            foreach ($queries as $query) {
                $commands[] = $this->query($run, $query, $queryTick);
            }
        }

        return $commands;
    }

    /**
     * @param list<CommandInterface> $messages
     * @param list<WorkflowCommand> $commands
     */
    private function exchange(RunState $run, array $messages, TickInfo $tick, array &$commands): void
    {
        while ($messages !== []) {
            $outgoing = ($this->dispatch)($messages, $this->headers);
            $messages = [];
            foreach ($outgoing as $command) {
                \array_push($messages, ...$this->translator->translate($run, $command, $tick, $commands));
            }
        }
    }

    private function query(RunState $run, QueryWorkflow $query, TickInfo $tick): WorkflowCommand
    {
        $request = new ServerRequest(
            name: 'InvokeQuery',
            info: $tick,
            options: ['runId' => $run->runId, 'name' => $query->getQueryType()],
            payloads: $this->payloads->values($query->getArguments()),
            id: $run->runId,
            header: $this->payloads->header($query->getHeaders()),
        );

        $result = new QueryResult(['query_id' => $query->getQueryId()]);
        foreach (($this->dispatch)([$request], $this->headers) as $response) {
            if ($response instanceof SuccessClientResponse) {
                $result->setSucceeded(new QuerySuccess(['response' => $this->payloads->firstPayload($response->getPayloads())]));
            } elseif ($response instanceof FailedClientResponse) {
                $result->setFailed($this->payloads->failure($response->getFailure()));
            }
        }

        if ($result->getVariant() === null || $result->getVariant() === '') {
            $result->setFailed($this->payloads->failure(new \LogicException('Query produced no result')));
        }

        return new WorkflowCommand(['respond_to_query' => $result]);
    }

    private function update(RunState $run, DoUpdate $update, TickInfo $tick): ServerRequest
    {
        $run->startUpdate($update->getId(), $update->getProtocolInstanceId());

        return new ServerRequest(
            name: 'InvokeUpdate',
            info: $tick,
            options: [
                'runId' => $run->runId,
                'updateId' => $update->getId(),
                'name' => $update->getName(),
                'replay' => !$update->getRunValidator(),
            ],
            payloads: $this->payloads->values($update->getInput()),
            id: $run->runId,
            header: $this->payloads->header($update->getHeaders()),
        );
    }

    private function startWorkflow(InitializeWorkflow $init, string $runId, TickInfo $tick): ServerRequest
    {
        $payloads = $this->payloads->values($init->getArguments());
        $options = ['info' => $this->info->workflowInfo($init, $runId, $this->namespace, $this->taskQueue)];

        $lastCompletion = $init->getLastCompletionResult()?->getPayloads();
        if ($lastCompletion !== null && \count($lastCompletion) > 0) {
            $all = new Payloads();
            $all->setPayloads([...$init->getArguments(), ...$lastCompletion]);
            $payloads = $this->payloads->values($all->getPayloads());
            $options['lastCompletion'] = \count($lastCompletion);
        }

        return new ServerRequest(
            name: 'StartWorkflow',
            info: $tick,
            options: $options,
            payloads: $payloads,
            id: $runId,
            header: $this->payloads->header($init->getHeaders()),
        );
    }
}
