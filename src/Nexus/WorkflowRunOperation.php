<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Api\Common\V1\Link\WorkflowEvent;
use Temporal\Api\Common\V1\Link\WorkflowEvent\EventReference;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Nexus\Internal\WorkflowRunOperationToken;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Workflow\CompletionCallback;
use Temporal\Workflow\OnConflictOptions;
use Temporal\Workflow\WorkflowExecution;

/**
 * Helpers that back a Nexus operation with a Temporal workflow run.
 * Use ::start() inside #[AsyncOperation], use ::cancel() inside #[OperationCancel].
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

        // Default task queue to the handler's queue; avoids silent hang on `default`.
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

            $callback = CompletionCallback::withNexusLinks($details->callbackUrl, $headers, $details->links);
            $options = $options->withCompletionCallbacks($callback);
        }

        // Required so a retried StartWorkflow attaches the new completion-callback to the existing run.
        $options = $options
            ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::UseExisting)
            ->withOnConflictOptions(new OnConflictOptions())
            ->withLinks($details->links);

        // Pin requestId so retried Nexus starts dedupe server-side.
        $options = $options->withRequestId($details->requestId);

        $stub = $client->newWorkflowStub($handle->workflowClass, $options);
        $run = $client->start($stub, ...$handle->args);

        // Self-link to WORKFLOW_EXECUTION_STARTED event of the run we just started.
        // Caller server attaches it to NEXUS_OPERATION_STARTED so UI shows the
        // caller↔handler chain. Mirror of Java/TS/Python/Go SDK behaviour.
        Nexus::getCurrentContext()->links->add(
            self::buildStartedEventSelfLink($nexusContext->namespace, $run->getExecution()),
        );

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

    private static function buildStartedEventSelfLink(string $namespace, WorkflowExecution $execution): Link
    {
        $event = (new WorkflowEvent())
            ->setNamespace($namespace)
            ->setWorkflowId($execution->getID())
            ->setRunId($execution->getRunID() ?? '');
        $event->setEventRef(
            (new EventReference())->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
        );
        return NexusLinkConverter::workflowEventToNexusLink($event);
    }
}
