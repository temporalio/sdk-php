<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Psr\Log\LoggerInterface;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\QueryWorkflowRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartBatchOperationRequest;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\UpdateScheduleRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Google\Protobuf\Internal\Message;
use Temporal\Common\PayloadLimitOptions;

/**
 * @internal
 */
final class PayloadSizeChecker
{
    private const MESSAGE_CODE = 'TMPRL1103';
    private const MAX_FAILURE_DEPTH = 20;

    public function __construct(
        private readonly PayloadLimitOptions $limits,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param non-empty-string $method
     */
    public function check(string $method, object $request): void
    {
        try {
            $this->inspect($method, $request);
        } catch (\Throwable) {
        }
    }

    private static function sizeOf(?Message $message): int
    {
        return $message === null ? 0 : \strlen($message->serializeToString());
    }

    private function inspect(string $method, object $request): void
    {
        switch (true) {
            case $request instanceof StartWorkflowExecutionRequest:
                $this->payloads($method, $request->getInput());
                $this->payloads($method, $request->getLastCompletionResult());
                $this->memo($method, $request->getMemo());
                return;

            case $request instanceof SignalWithStartWorkflowExecutionRequest:
                $this->payloads($method, $request->getInput());
                $this->payloads($method, $request->getSignalInput());
                $this->memo($method, $request->getMemo());
                return;

            case $request instanceof ExecuteMultiOperationRequest:
                foreach ($request->getOperations() as $operation) {
                    $nested = $operation->getStartWorkflow() ?? $operation->getUpdateWorkflow();
                    if ($nested !== null) {
                        $this->inspect($method, $nested);
                    }
                }
                return;

            case $request instanceof SignalWorkflowExecutionRequest:
                $this->payloads($method, $request->getInput());
                return;

            case $request instanceof UpdateWorkflowExecutionRequest:
                $this->payloads($method, $request->getRequest()?->getInput()?->getArgs());
                return;

            case $request instanceof QueryWorkflowRequest:
                $this->payloads($method, $request->getQuery()?->getQueryArgs());
                return;

            case $request instanceof RespondActivityTaskCompletedRequest:
            case $request instanceof RespondActivityTaskCompletedByIdRequest:
                $this->payloads($method, $request->getResult());
                return;

            case $request instanceof RespondActivityTaskFailedRequest:
            case $request instanceof RespondActivityTaskFailedByIdRequest:
                $this->failure($method, $request->getFailure());
                $this->payloads($method, $request->getLastHeartbeatDetails());
                return;

            case $request instanceof RespondActivityTaskCanceledRequest:
            case $request instanceof RespondActivityTaskCanceledByIdRequest:
            case $request instanceof RecordActivityTaskHeartbeatRequest:
            case $request instanceof RecordActivityTaskHeartbeatByIdRequest:
            case $request instanceof TerminateWorkflowExecutionRequest:
                $this->payloads($method, $request->getDetails());
                return;

            case $request instanceof CreateScheduleRequest:
                $action = $request->getSchedule()?->getAction()?->getStartWorkflow();
                if ($action === null) {
                    return;
                }

                $this->warn(
                    $method,
                    'payloads',
                    self::sizeOf($request->getMemo()) + self::sizeOf($action->getInput()),
                    $this->limits->payloadSizeWarning,
                );
                return;

            case $request instanceof StartBatchOperationRequest:
                $this->payloads($method, $request->getSignalOperation()?->getInput());
                $this->payloads($method, $request->getTerminationOperation()?->getDetails());
                return;

            case $request instanceof ResetWorkflowExecutionRequest:
                foreach ($request->getPostResetOperations() as $operation) {
                    $this->payloads($method, $operation->getSignalWorkflow()?->getInput());
                }
                return;

            case $request instanceof UpdateScheduleRequest:
                $action = $request->getSchedule()?->getAction()?->getStartWorkflow();
                $this->payloads($method, $action?->getInput());
                $this->memo($method, $action?->getMemo());
                return;
        }
    }

    private function failure(string $method, ?Failure $failure, int $depth = 0): void
    {
        if ($failure === null || $depth >= self::MAX_FAILURE_DEPTH) {
            return;
        }

        $this->payloads($method, $failure->getApplicationFailureInfo()?->getDetails());
        $this->payloads($method, $failure->getCanceledFailureInfo()?->getDetails());
        $this->payloads($method, $failure->getTimeoutFailureInfo()?->getLastHeartbeatDetails());
        $this->payloads($method, $failure->getResetWorkflowFailureInfo()?->getLastHeartbeatDetails());

        $this->failure($method, $failure->getCause(), $depth + 1);
    }

    private function payloads(string $method, ?Payloads $payloads): void
    {
        $this->warn($method, 'payloads', self::sizeOf($payloads), $this->limits->payloadSizeWarning);
    }

    private function memo(string $method, ?Memo $memo): void
    {
        $this->warn($method, 'memo', self::sizeOf($memo), $this->limits->memoSizeWarning);
    }

    /**
     * @param non-empty-string $kind
     */
    private function warn(string $method, string $kind, int $size, ?int $limit): void
    {
        if ($limit === null || $size <= $limit) {
            return;
        }

        $this->logger->warning(
            \sprintf(
                '[%s] Attempted to upload %s with size that exceeded the warning limit.',
                self::MESSAGE_CODE,
                $kind,
            ),
            ['method' => $method, 'size' => $size, 'limit' => $limit],
        );
    }
}
