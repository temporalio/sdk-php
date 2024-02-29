<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Update\UpdateHandle;
use Temporal\Client\Update\UpdateOptions;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\IllegalStateException;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\UpdateMethod;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;

/**
 * WorkflowStub is a client side stub to a single workflow instance. It can be
 * used to start, signal, query, wait for completion and cancel a workflow
 * execution. Created through {@see WorkflowClient::newUntypedWorkflowStub()}.
 */
interface WorkflowStubInterface extends WorkflowRunInterface
{
    /**
     * @return string|null
     */
    public function getWorkflowType(): ?string;

    /**
     * Returns associated workflow options. Empty for running workflows. Workflow options are immutable and can
     * not be changed after the workflow was created.
     *
     * @return WorkflowOptions|null
     */
    public function getOptions(): ?WorkflowOptions;

    /**
     * Get associated workflow execution (if any).
     *
     * @throws IllegalStateException
     */
    public function getExecution(): WorkflowExecution;

    /**
     * Check if workflow was stater and has associated execution.
     *
     * @return bool
     */
    public function hasExecution(): bool;

    /**
     * Attaches running workflow context to the workflow stub.
     *
     * @param WorkflowExecution $execution
     */
    public function setExecution(WorkflowExecution $execution): void;

    /**
     * Sends named signal to the workflow execution.
     *
     * @param string $name
     * @param mixed ...$args
     */
    public function signal(string $name, ...$args): void;

    /**
     * Synchronously queries workflow by invoking its query handler. Usually a
     * query handler is a method annotated with {@see QueryMethod}.
     *
     * @param string $name
     * @param mixed ...$args
     * @return ValuesInterface|null
     */
    public function query(string $name, ...$args): ?ValuesInterface;

    /**
     * Synchronously update a workflow execution by invoking its update handler.
     * Usually an update handler is a method annotated with the {@see UpdateMethod} attribute.
     *
     * @param non-empty-string $name Name of the update handler.
     * @param mixed ...$args Arguments to pass to the update handler.
     * @return ValuesInterface|null
     */
    public function update(string $name, ...$args): ?ValuesInterface;

    /**
     * Asynchronously update a workflow execution by invoking its update handler and returning a
     * handle to the update request.
     * Usually an update handler is a method annotated with the {@see UpdateMethod} attribute.
     *
     * @param non-empty-string|UpdateOptions $nameOrOptions Name of the update handler or update options.
     * @param mixed ...$args Arguments to pass to the update handler.
     * @return UpdateHandle
     */
    public function startUpdate(string|UpdateOptions $nameOrOptions, ...$args): UpdateHandle;

    /**
     * Request cancellation of a workflow execution.
     *
     * Cancellation cancels {@see CancellationScopeInterface} that wraps the
     * main workflow method. Note that workflow can take long time to get
     * canceled or even completely ignore the cancellation request.
     *
     * @return void
     */
    public function cancel(): void;

    /**
     * Terminates a workflow execution.
     *
     * Termination is a hard stop of a workflow execution which doesn't give
     * workflow code any chance to perform cleanup.
     *
     * @param string $reason
     * @param array $details
     * @return void
     */
    public function terminate(string $reason, array $details = []): void;
}
