<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Internal\Nexus\WorkflowRunOperationToken;

/**
 * Backs a Nexus operation with a Temporal workflow.
 *
 * The factory builds {@see WorkflowHandle} (class+options+args).
 * This handler then layers Nexus concerns on top: requestId pinning,
 * completion callback, default task queue, async token encoding.
 * Cancel decodes the token and cancels the workflow.
 *
 * @since Nexus support
 */
final class WorkflowRunOperation
{
    /**
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

                // Default task queue to the handler's queue (Java parity); avoids silent hang on `default`.
                if ($options->taskQueue === \Temporal\Worker\WorkerFactoryInterface::DEFAULT_TASK_QUEUE) {
                    $options = $options->withTaskQueue($nexusCtx->taskQueue);
                }

                // Token = ns+workflowId (stable across retries).
                $token = WorkflowRunOperationToken::generate(
                    $nexusCtx->namespace,
                    $options->workflowId,
                );

                if ($details->callbackUrl !== null && $details->callbackUrl !== '') {
                    $headers = $details->callbackHeaders;
                    // Send both header names for pre/post-1.27 server compatibility.
                    $headers['Nexus-Operation-Token'] = $token;
                    $headers['nexus-operation-id'] = $token;

                    $options = $options->withNexusCompletionCallback($details->callbackUrl, $headers);
                }

                // Pin requestId so retried Nexus starts dedupe server-side.
                $options = $options->withRequestId($details->requestId);

                $stub = $client->newWorkflowStub($handle->workflowClass, $options);
                $client->start($stub, ...$handle->args);

                return OperationStartResult::async(new OperationInfo($token, OperationState::Running));
            }

            public function cancel(
                OperationContext $context,
                OperationCancelDetails $details,
            ): void {
                $nexusCtx = Nexus::getOperationContext();
                $decoded = WorkflowRunOperationToken::load($details->operationToken);

                if ($decoded->namespace !== '' && $decoded->namespace !== $nexusCtx->namespace) {
                    throw new \InvalidArgumentException(\sprintf(
                        'workflow run token namespace "%s" does not match handler namespace "%s"',
                        $decoded->namespace,
                        $nexusCtx->namespace,
                    ));
                }

                $stub = $nexusCtx->workflowClient->newUntypedRunningWorkflowStub($decoded->workflowId);
                $stub->cancel();
            }
        };
    }
}
