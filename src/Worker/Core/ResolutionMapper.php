<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Activity_result\ActivityResolution;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecution;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStart;
use Temporal\Api\Enums\V1\RetryState;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow\WorkflowExecution;

final class ResolutionMapper
{
    private const UNKNOWN_EVENT_ID = 0;
    private const CHILD_ALREADY_STARTED_MESSAGE = 'Workflow execution already started';
    private const CHILD_ALREADY_STARTED_TYPE = 'ChildWorkflowExecutionAlreadyStartedError';

    public function __construct(
        private readonly PayloadMapper $payloads,
        private readonly string $namespace,
    ) {}

    public function activity(RunState $run, int $seq, ActivityResolution $result, TickInfo $tick): CommandInterface
    {
        $requestId = $run->release($seq);
        $tryCancel = $run->takeTryCancel($seq);
        $run->localActivities->forget($seq);

        return match ($result->getStatus()) {
            'completed' => new SuccessResponse($this->payloads->valuesFromPayload($result->getCompleted()->getResult()), $requestId, $tick),
            'failed' => new FailureResponse($this->payloads->exception($result->getFailed()->getFailure()), $requestId, $tick),
            'cancelled' => new FailureResponse($this->cancelledActivity($result->getCancelled()->getFailure(), $tryCancel), $requestId, $tick),
        };
    }

    /**
     * @return list<CommandInterface>
     */
    public function childStarted(RunState $run, ResolveChildWorkflowExecutionStart $start, TickInfo $tick): array
    {
        $childId = $run->requestId($start->getSeq());
        $messages = [];

        switch ($start->getStatus()) {
            case 'succeeded':
                $execution = $run->childStarted($childId, $start->getSucceeded()->getRunId());
                break;
            case 'failed':
                $failed = $start->getFailed();
                $run->release($start->getSeq());
                $execution = $this->childAlreadyStarted($failed->getWorkflowType(), $failed->getWorkflowId());
                $messages[] = new FailureResponse($execution, $childId, $tick);
                break;
            default:
                $run->release($start->getSeq());
                $execution = $this->payloads->exception($start->getCancelled()->getFailure());
                $messages[] = new FailureResponse($execution, $childId, $tick);
                break;
        }

        foreach ($run->takeChildWaiters($childId) as $waiter) {
            $messages[] = $this->childExecution($waiter, $execution, $tick);
        }
        if ($execution instanceof \Throwable) {
            $run->forgetChild($childId);
        }

        return $messages;
    }

    /**
     * @param array{string, string}|\Throwable $execution
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function childExecution(int $requestId, array|\Throwable $execution, TickInfo $tick): CommandInterface
    {
        if ($execution instanceof \Throwable) {
            return new FailureResponse($execution, $requestId, $tick);
        }

        return new SuccessResponse($this->payloads->encode([new WorkflowExecution($execution[0], $execution[1])]), $requestId, $tick);
    }

    public function childResult(RunState $run, ResolveChildWorkflowExecution $resolve, TickInfo $tick): CommandInterface
    {
        $requestId = $run->release($resolve->getSeq());
        $run->forgetChild($requestId);
        $result = $resolve->getResult();

        return match ($result->getStatus()) {
            'completed' => new SuccessResponse($this->payloads->valuesFromPayload($result->getCompleted()->getResult()), $requestId, $tick),
            'failed' => new FailureResponse($this->payloads->exception($result->getFailed()->getFailure()), $requestId, $tick),
            default => new FailureResponse($this->payloads->exception($result->getCancelled()->getFailure()), $requestId, $tick),
        };
    }

    public function external(int $requestId, ?Failure $failure, TickInfo $tick): CommandInterface
    {
        return $failure === null
            ? new SuccessResponse(null, $requestId, $tick)
            : new FailureResponse($this->payloads->exception($failure), $requestId, $tick);
    }

    private function childAlreadyStarted(string $workflowType, string $workflowId): ChildWorkflowFailure
    {
        return new ChildWorkflowFailure(
            initiatedEventId: self::UNKNOWN_EVENT_ID,
            startedEventId: self::UNKNOWN_EVENT_ID,
            workflowType: $workflowType,
            execution: new WorkflowExecution($workflowId),
            namespace: $this->namespace,
            retryState: RetryState::RETRY_STATE_UNSPECIFIED,
            previous: new ApplicationFailure(self::CHILD_ALREADY_STARTED_MESSAGE, self::CHILD_ALREADY_STARTED_TYPE, true),
        );
    }

    private function cancelledActivity(Failure $failure, bool $tryCancel): \Throwable
    {
        $error = $this->payloads->exception($failure);

        return $tryCancel && $error->getPrevious() instanceof CanceledFailure ? $error->getPrevious() : $error;
    }
}
