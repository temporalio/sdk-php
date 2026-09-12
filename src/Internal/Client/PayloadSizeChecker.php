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
 * Warns when an outgoing gRPC request carries payloads larger than the configured limits.
 *
 * Sizes are measured the way the server measures them: a `Payloads` or `Memo` message as a whole,
 * a failure through every `details` of its cause chain.
 *
 * Requests are inspected field by field rather than by walking protobuf descriptors: the descriptor
 * API differs between the pure PHP implementation and the `protobuf` extension.
 *
 * Search attributes are not measured here: the server keeps a separate limit for them, and the
 * other SDKs do not report them on client requests either.
 *
 * @internal
 */
final class PayloadSizeChecker
{
    /**
     * Message code used by all the SDKs for the payload size warning.
     */
    private const MESSAGE_CODE = 'TMPRL1103';

    /**
     * Failures nest through `cause`, so the chain is walked with a limit.
     */
    private const MAX_FAILURE_DEPTH = 20;

    public function __construct(
        private readonly PayloadLimitOptions $limits,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param non-empty-string $method RPC method name.
     */
    public function check(string $method, object $request): void
    {
        if (!$this->limits->isEnabled()) {
            return;
        }

        try {
            $this->inspect($method, $request);
        } catch (\Throwable) {
            // Measuring is an observability feature: it must never break the RPC call
        }
    }

    /**
     * Size of a message as the server sees it on the wire.
     */
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
                // The server checks the memo of the request and the workflow input as one value,
                // and it supports no other action, so anything else is left to it
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
                // Nothing nested in the request is measured again: the server has no separate
                // check for the memo of the action, and the other SDKs do not report it either
                return;

            case $request instanceof StartBatchOperationRequest:
                $this->payloads($method, $request->getSignalOperation()?->getInput());
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

    /**
     * Every `details` of a failure and of its causes is measured on its own, as the server does.
     */
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
