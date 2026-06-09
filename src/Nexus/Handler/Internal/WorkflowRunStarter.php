<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Api\Common\V1\Link\WorkflowEvent;
use Temporal\Api\Common\V1\Link\WorkflowEvent\EventReference;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Internal\Client\OnConflictOptions;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Header;
use Temporal\Nexus\Internal\Headers;
use Temporal\Nexus\Internal\WorkflowRunOperationToken;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Workflow\CompletionCallback;
use Temporal\Workflow\WorkflowExecution;

/**
 * Starts the backing workflow described by a {@see WorkflowHandle} returned from
 * an #[AsyncOperation] method, layering Nexus concerns on top of the user-supplied
 * options: requestId pinning, completion callback, default task queue, async token
 * encoding.
 *
 * @internal
 */
final class WorkflowRunStarter
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function start(WorkflowHandle $handle, OperationStartDetails $details): OperationInfo
    {
        $client = Nexus::getWorkflowClient();
        $info = Nexus::getOperationContext();

        $options = $handle->options;
        if ($options->workflowId === '') {
            throw new \LogicException(\sprintf(
                'Async Nexus operation: workflow ID is required for %s — '
                . 'set it via WorkflowOptions::withWorkflowId($details->requestId) inside your handler.',
                $handle->workflowClass,
            ));
        }

        // Default task queue to the handler's queue; avoids silent hang on `default`.
        if ($options->taskQueue === WorkerFactoryInterface::DEFAULT_TASK_QUEUE) {
            $options = $options->withTaskQueue($info->taskQueue);
        }

        $token = WorkflowRunOperationToken::generate(
            $info->namespace,
            $options->workflowId,
        );

        if ($details->callbackUrl !== null && $details->callbackUrl !== '') {
            $headers = $details->callbackHeaders;
            $present = Headers::normalize($headers);
            // Send both header names for pre/post-1.27 server compatibility.
            if (!\array_key_exists(\strtolower(Header::OPERATION_TOKEN), $present)) {
                $headers[Header::OPERATION_TOKEN] = $token;
            }
            if (!\array_key_exists(\strtolower(Header::OPERATION_ID), $present)) {
                $headers[\strtolower(Header::OPERATION_ID)] = $token;
            }

            $callback = CompletionCallback::fromNexusLinks($details->callbackUrl, $headers, $details->links);
            $options = $options->withCompletionCallbacks($callback);
        }

        $options = $options
            ->withLinks($details->links)
            ->withOnConflictOptionsInternal(new OnConflictOptions())
        // Pin requestId so retried Nexus starts dedupe server-side.
            ->withRequestId($details->requestId);

        $stub = $client->newWorkflowStub($handle->workflowClass, $options);
        $run = $client->start($stub, ...$handle->args);

        Nexus::getCurrentOperationContext()
            ->links
            ->add(self::buildStartedEventSelfLink($info->namespace, $run->getExecution()));

        return new OperationInfo($token, OperationState::Running);
    }

    private static function buildStartedEventSelfLink(string $namespace, WorkflowExecution $execution): Link
    {
        $event = (new WorkflowEvent())
            ->setNamespace($namespace)
            ->setWorkflowId($execution->getID())
            ->setRunId($execution->getRunID() ?? '')
            ->setEventRef(
                (new EventReference())
                    ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED),
            );
        return NexusLinkConverter::workflowEventToNexusLink($event);
    }
}
