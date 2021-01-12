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

/**
 * Client to the Temporal service used to start and query workflows by external processes. Also it
 * supports creation of {@link ActivityCompletionClient} instances used to complete activities
 * asynchronously. Do not create this object for each request, keep it for the duration of the
 * process.
 *
 * <p>Given a workflow interface executing a workflow requires initializing a {@link
 * io.temporal.client.WorkflowClient} instance, creating a client side stub to the workflow, and
 * then calling a method annotated with {@literal @}{@link WorkflowMethod}.
 *
 * <pre><code>
 * $workflowClient = new WorkflowClient($serviceClient, ClientOptions::new()->withNamespace('default'));
 *
 * // Create a workflow stub.
 * $fileWorkflow = $workflowClient->newWorkflowStub(FileProcessingWorkflow::class);
 * </code></pre>
 *
 * There are two ways to start workflow execution: synchronously and asynchronously. Synchronous
 * invocation starts a workflow and then waits for its completion. If the process that started the
 * workflow crashes or stops waiting, the workflow continues executing. Because workflows are
 * potentially long running, and crashes of clients happen, it is not very commonly found in
 * production use. Asynchronous start initiates workflow execution and immediately returns to the
 * caller. This is the most common way to start workflows in production code.
 *
 * <p>Synchronous start:
 *
 * <pre><code>
 * // Start a workflow and wait for a result.
 * // Note that if the waiting process is killed, the workflow will continue executing.
 * String result = workflow.processFile(workflowArgs);
 * </code></pre>
 *
 * Asynchronous when the workflow result is not needed:
 *
 * <pre><code>
 * // Returns as soon as the workflow starts.
 * WorkflowExecution workflowExecution = WorkflowClient.start(workflow::processFile, workflowArgs);
 *
 * System.out.println("Started process file workflow with workflowId=\"" + workflowExecution.getWorkflowId()
 *                     + "\" and runId=\"" + workflowExecution.getRunId() + "\"");
 * </code></pre>
 *
 * Asynchronous when the result is needed:
 *
 * <pre><code>
 * CompletableFuture&lt;String&gt; result = WorkflowClient.execute(workflow::helloWorld, "User");
 * </code></pre>
 *
 * If you need to wait for a workflow completion after an asynchronous start, maybe even from a
 * different process, the simplest way is to call the blocking version again. If {@link
 * WorkflowOptions#getWorkflowIdReusePolicy()} is not {@code AllowDuplicate} then instead of
 * throwing {@link WorkflowExecutionAlreadyStarted}, it reconnects to an existing workflow and waits
 * for its completion. The following example shows how to do this from a different process than the
 * one that started the workflow. All this process needs is a {@code WorkflowId}.
 *
 * <pre><code>
 * FileProcessingWorkflow workflow = workflowClient.newWorkflowStub(FileProcessingWorkflow.class, workflowId);
 * // Returns result potentially waiting for workflow to complete.
 * String result = workflow.processFile(workflowArgs);
 * </code></pre>
 *
 * @see io.temporal.workflow.Workflow
 * @see Activity
 * @see io.temporal.worker.Worker
 */
interface WorkflowClientInterface
{
    /**
     * @return ServiceClientInterface
     */
    public function getServiceClient(): ServiceClientInterface;

    /**
     * Creates workflow client stub that can be used to start a single workflow execution. The first
     * call must be to a method annotated with @WorkflowMethod. After workflow is started it can be
     * also used to send signals or queries to it. IMPORTANT! Stub is per workflow instance. So new
     * stub should be created for each new one.
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param WorkflowOptions|null $options
     * @return object<T>|T
     */
    public function newWorkflowStub(
        string $class,
        WorkflowOptions $options = null
    ): object;

    /**
     * Creates workflow untyped client stub that can be used to start a single workflow execution.
     * After workflow is started it can be also used to send signals or queries to it. IMPORTANT! Stub
     * is per workflow instance. So new stub should be created for each new one.
     *
     * @param string $name
     * @param WorkflowOptions|null $options
     * @return WorkflowStubInterface
     */
    public function newUntypedWorkflowStub(
        string $name,
        WorkflowOptions $options = null
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
