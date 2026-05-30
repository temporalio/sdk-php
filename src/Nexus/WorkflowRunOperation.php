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
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Internal\Nexus\OnConflictOptions;
use Temporal\Nexus\Internal\WorkflowRunOperationToken;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Workflow\CompletionCallback;
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
        $client = Nexus::getWorkflowClient();
        $info = Nexus::getOperationContext();

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
            $options = $options->withTaskQueue($info->taskQueue);
        }

        $token = WorkflowRunOperationToken::generate(
            $info->namespace,
            $options->workflowId,
        );

        if ($details->callbackUrl !== null && $details->callbackUrl !== '') {
            $headers = $details->callbackHeaders;
            $present = \array_change_key_case($headers, \CASE_LOWER);
            // Send both header names for pre/post-1.27 server compatibility.
            if (!\array_key_exists('nexus-operation-token', $present)) {
                $headers['Nexus-Operation-Token'] = $token;
            }
            if (!\array_key_exists('nexus-operation-id', $present)) {
                $headers['nexus-operation-id'] = $token;
            }

            $callback = CompletionCallback::withNexusLinks($details->callbackUrl, $headers, $details->links);
            $options = $options->withCompletionCallbacks($callback);
        }

        $options = $options
            ->withLinks($details->links)
            ->withOnConflictOptionsInternal(OnConflictOptions::forNexusCompletionCallback());

        // Pin requestId so retried Nexus starts dedupe server-side.
        $options = $options->withRequestId($details->requestId);

        $stub = $client->newWorkflowStub($handle->workflowClass, $options);
        $run = $client->start($stub, ...$handle->args);

        Nexus::getCurrentOperationContext()->links->add(
            self::buildStartedEventSelfLink($info->namespace, $run->getExecution()),
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
        $client = Nexus::getWorkflowClient();
        $info = Nexus::getOperationContext();
        $decoded = WorkflowRunOperationToken::load($operationToken);

        if ($decoded->namespace !== '' && $decoded->namespace !== $info->namespace) {
            throw new InvalidArgumentException(\sprintf(
                'workflow run token namespace "%s" does not match handler namespace "%s"',
                $decoded->namespace,
                $info->namespace,
            ));
        }

        $stub = $client->newUntypedRunningWorkflowStub($decoded->workflowId);
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
