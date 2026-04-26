<?php

declare(strict_types=1);

namespace Temporal\Nexus;

use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\OperationStartResult;
use Nexus\Sdk\OperationInfo;
use Nexus\Sdk\OperationState;
use Temporal\Internal\Nexus\WorkflowRunOperationToken;

/**
 * Maps a Nexus operation to a Temporal workflow run.
 *
 * Mirrors Java `io.temporal.nexus.WorkflowRunOperation` and Go
 * `temporalnexus.NewWorkflowRunOperation`. The handler:
 *
 *   1. Asks the user factory for a {@see WorkflowHandle} (class + options + args).
 *   2. Layers Nexus-side concerns on top:
 *      - `requestId` from {@see OperationStartDetails::$requestId} (so retries dedupe server-side)
 *      - completion callback from `callbackUrl` + `callbackHeaders` so the
 *        Temporal server delivers the workflow's result back to the Nexus
 *        caller
 *   3. Starts the workflow via the worker-supplied {@see \Temporal\Client\WorkflowClientInterface}.
 *   4. Encodes the operation token (`base64url(JSON{t,ns,wid})`) — same format
 *      as Java/Go so a PHP-issued token is decodable on any Temporal-aware
 *      caller.
 *
 * Cancel decodes the token and calls `cancelWorkflow($workflowId)` on the
 * same client.
 *
 * @since Nexus support
 */
final class WorkflowRunOperation
{
    /**
     * Build an {@see OperationHandlerInterface} that starts a workflow on each
     * `start()` call.
     *
     * @template I
     * @param callable(OperationContext, OperationStartDetails, I|null): WorkflowHandle $factory
     */
    public static function fromWorkflowMethod(callable $factory): OperationHandlerInterface
    {
        return new class($factory) implements OperationHandlerInterface {
            /** @var callable */
            private $factory;

            public function __construct(callable $factory)
            {
                $this->factory = $factory;
            }

            public function start(
                OperationContext $context,
                OperationStartDetails $details,
                mixed $param,
            ): OperationStartResult {
                $nexusCtx = Nexus::getOperationContext();
                $client = $nexusCtx->workflowClient;

                /** @var WorkflowHandle $handle */
                $handle = ($this->factory)($context, $details, $param);
                if (!$handle instanceof WorkflowHandle) {
                    throw new \LogicException(
                        'WorkflowRunOperation factory must return a ' . WorkflowHandle::class
                        . ', got ' . \get_debug_type($handle),
                    );
                }

                $options = $handle->options;
                if ($options->workflowId === '') {
                    throw new \LogicException(
                        'WorkflowRunOperation: workflow ID is required — set it via '
                        . 'WorkflowOptions::withWorkflowId($details->requestId) inside your factory.',
                    );
                }

                // Task queue defaults to the queue this operation is handled on, matching
                // Java's WorkflowRunOperation behavior. Without this, workflows would land on
                // the unintended `default` queue and silently hang because no worker polls it.
                // The user can override by setting taskQueue explicitly in their factory.
                if ($options->taskQueue === \Temporal\Worker\WorkerFactoryInterface::DEFAULT_TASK_QUEUE) {
                    $options = $options->withTaskQueue($nexusCtx->taskQueue);
                }

                // Token must be derived from a stable workflow id, *not* the run id —
                // the Nexus caller may retry start before the server-side workflow is
                // visible, and the canonical reference (Go `temporalnexus/token.go`)
                // hashes only namespace + workflow id.
                $token = WorkflowRunOperationToken::generate(
                    $nexusCtx->namespace,
                    $options->workflowId,
                );

                if ($details->callbackUrl !== null && $details->callbackUrl !== '') {
                    $headers = $details->callbackHeaders;
                    // Server-side completion handler reads either header — the
                    // legacy `nexus-operation-id` was renamed to
                    // `Nexus-Operation-Token` in 1.27. Sending both keeps the
                    // handler compatible across versions.
                    $headers['Nexus-Operation-Token'] = $token;
                    $headers['nexus-operation-id'] = $token;

                    $options = $options->withNexusCompletionCallback($details->callbackUrl, $headers);
                }

                if ($details->requestId !== '') {
                    // request_id pinning is what dedupes a retried Nexus start
                    // on the server side. Without it, a flaky network would
                    // produce a fresh workflow execution on every retry.
                    $options = $options->withRequestId($details->requestId);
                }

                $stub = $client->newWorkflowStub($handle->workflowClass, $options);

                // We don't await the run — `start()` returns the WorkflowRun
                // handle synchronously after the server ack. The Nexus token
                // is already bound to the workflow id we just sent.
                $client->start($stub, ...$handle->args);

                return OperationStartResult::async(new OperationInfo($token, OperationState::Running));
            }

            public function cancel(
                OperationContext $context,
                OperationCancelDetails $details,
            ): void {
                $nexusCtx = Nexus::getOperationContext();
                $decoded = WorkflowRunOperationToken::load($details->operationToken);

                $stub = $nexusCtx->workflowClient->newUntypedRunningWorkflowStub($decoded->workflowId);
                $stub->cancel();
            }

            public static function sync(callable $function): OperationHandlerInterface
            {
                throw new \LogicException(
                    'WorkflowRunOperation::sync() — use SynchronousOperationHandler for sync operations '
                    . 'and WorkflowRunOperation::fromWorkflowMethod() for workflow-backed ones.',
                );
            }
        };
    }
}
