<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Internal\Nexus\WorkflowRunOperationToken;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Workflow\OnConflictOptions;

/**
 * Helpers that back a Nexus operation with a Temporal workflow run.
 *
 * Use {@see self::start()} from inside an `#[AsyncOperation]` method to start
 * the backing workflow and return the {@see OperationInfo} the framework needs.
 * Use {@see self::cancel()} from inside an `#[OperationCancel]` method to cancel
 * the workflow by operation token.
 */
final class WorkflowRunOperation
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Start the backing workflow described by $handle and return the
     * {@see OperationInfo} carrying the operation token.
     *
     * Layers Nexus concerns on top of the user-supplied options: requestId
     * pinning, completion callback, default task queue, async token encoding.
     */
    public static function start(WorkflowHandle $handle, OperationStartDetails $details): OperationInfo
    {
        $nexusContext = Nexus::getOperationContext();
        $client = $nexusContext->workflowClient;

        $options = $handle->options;
        if ($options->workflowId === '') {
            throw new \LogicException(\sprintf(
                'WorkflowRunOperation::start(): workflow ID is required for %s — '
                . 'set it via WorkflowOptions::withWorkflowId($details->requestId) inside your handler.',
                $handle->workflowClass,
            ));
        }

        // Default task queue to the handler's queue (Java parity); avoids silent hang on `default`.
        if ($options->taskQueue === \Temporal\Worker\WorkerFactoryInterface::DEFAULT_TASK_QUEUE) {
            $options = $options->withTaskQueue($nexusContext->taskQueue);
        }

        // Token = ns+workflowId (stable across retries).
        $token = WorkflowRunOperationToken::generate(
            $nexusContext->namespace,
            $options->workflowId,
        );

        if ($details->callbackUrl !== null && $details->callbackUrl !== '') {
            $headers = $details->callbackHeaders;
            // Send both header names for pre/post-1.27 server compatibility.
            $headers['Nexus-Operation-Token'] = $token;
            $headers['nexus-operation-id'] = $token;

            $options = $options->withNexusCompletionCallback($details->callbackUrl, $headers);
        }

        // Required so a retried StartWorkflow attaches the new completion-callback to the existing run.
        $options = $options
            ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::UseExisting)
            ->withOnConflictOptions(new OnConflictOptions());

        // Pin requestId so retried Nexus starts dedupe server-side.
        $options = $options->withRequestId($details->requestId);

        $stub = $client->newWorkflowStub($handle->workflowClass, $options);
        $client->start($stub, ...$handle->args);

        return new OperationInfo($token, OperationState::Running);
    }

    /**
     * Cancel the workflow corresponding to the given operation token.
     *
     * Decodes the token and asks the workflow client to cancel by workflow ID.
     */
    public static function cancel(string $operationToken): void
    {
        $nexusContext = Nexus::getOperationContext();
        $decoded = WorkflowRunOperationToken::load($operationToken);

        if ($decoded->namespace !== '' && $decoded->namespace !== $nexusContext->namespace) {
            throw new InvalidArgumentException(\sprintf(
                'workflow run token namespace "%s" does not match handler namespace "%s"',
                $decoded->namespace,
                $nexusContext->namespace,
            ));
        }

        $stub = $nexusContext->workflowClient->newUntypedRunningWorkflowStub($decoded->workflowId);
        $stub->cancel();
    }
}
