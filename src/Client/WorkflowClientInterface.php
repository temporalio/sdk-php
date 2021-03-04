<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Workflow\WorkflowRunInterface;

interface WorkflowClientInterface
{
    /**
     * @return ServiceClientInterface
     */
    public function getServiceClient(): ServiceClientInterface;

    /**
     * Starts untyped and typed workflow stubs in async mode.
     *
     * @param WorkflowStubInterface|object $workflow
     * @param mixed $args
     * @return WorkflowRunInterface
     */
    public function start($workflow, ...$args): WorkflowRunInterface;

    /**
     * Starts untyped and typed workflow stubs in async mode. Sends signal on start.
     *
     * @param object|WorkflowStubInterface $workflow
     * @param string $signal
     * @param array $signalArgs
     * @param array $startArgs
     * @return WorkflowRunInterface
     */
    public function startWithSignal(
        $workflow,
        string $signal,
        array $signalArgs = [],
        array $startArgs = []
    ): WorkflowRunInterface;

    /**
     * Creates workflow client stub that can be used to start a single workflow
     * execution. The first call must be to a method annotated
     * with {@see WorkflowMethod}. After workflow is started it can be also
     * used to send signals or queries to it.
     *
     * Use WorkflowClient->start($workflowStub, ...$args) to start workflow asynchronously.
     *
     * IMPORTANT! Stub is per workflow instance. So new stub should be created
     * for each new one.
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param WorkflowOptions|null $options
     * @return T
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): object;

    /**
     * Creates workflow untyped client stub that can be used to start a single
     * workflow execution. After workflow is started it can be also used to send
     * signals or queries to it.
     *
     * Use WorkflowClient->start($workflowStub, ...$args) to start workflow asynchronously.
     *
     * IMPORTANT! Stub is per workflow instance. So new stub should be created
     * for each new one.
     *
     * @param string $workflowType
     * @param WorkflowOptions|null $options
     * @return WorkflowStubInterface
     */
    public function newUntypedWorkflowStub(
        string $workflowType,
        WorkflowOptions $options = null
    ): WorkflowStubInterface;

    /**
     * Returns workflow stub associated with running workflow.
     *
     * @psalm-template T of object
     * @param string $class
     * @param string $workflowID
     * @param string|null $runID
     * @return T
     */
    public function newRunningWorkflowStub(
        string $class,
        string $workflowID,
        ?string $runID = null
    ): object;

    /**
     * Returns untyped workflow stub associated with running workflow.
     *
     * @param string $workflowID
     * @param string|null $runID
     * @param string|null $workflowType
     * @return WorkflowStubInterface
     */
    public function newUntypedRunningWorkflowStub(
        string $workflowID,
        ?string $runID = null,
        ?string $workflowType = null
    ): WorkflowStubInterface;

    /**
     * Creates new {@link ActivityCompletionClient} that can be used to complete activities
     * asynchronously. Only relevant for activity implementations that called {@link
     * ActivityContext->doNotCompleteOnReturn()}.
     *
     * @return ActivityCompletionClientInterface
     */
    public function newActivityCompletionClient(): ActivityCompletionClientInterface;
}
