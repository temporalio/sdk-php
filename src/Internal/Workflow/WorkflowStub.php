<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Worker\Transport\RpcConnectionInterface;
use Temporal\Workflow\WorkflowExecution;

class WorkflowStub implements WorkflowStubInterface
{
    /**
     * @var string
     */
    private const ERROR_WORKFLOW_START_DUPLICATION =
        'Cannot reuse a stub instance to start more than one workflow execution. ' .
        'The stub points to already started execution. If you are trying to wait ' .
        'for a workflow completion either change WorkflowIdReusePolicy from ' .
        'AllowDuplicate or use WorkflowStub.getResult';

    /**
     * @var string
     */
    private const ERROR_WORKFLOW_NOT_STARTED =
        'Method "%s" cannot be called because the workflow has not been started';

    /**
     * @var string
     */
    private string $workflow;

    /**
     * @var WorkflowOptions
     */
    private WorkflowOptions $options;

    /**
     * @var WorkflowExecution|null
     */
    private ?WorkflowExecution $execution = null;

    /**
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param RpcConnectionInterface $rpc
     * @param MarshallerInterface $marshaller
     * @param string $workflow
     * @param WorkflowOptions $options
     */
    public function __construct(
        RpcConnectionInterface $rpc,
        MarshallerInterface $marshaller,
        string $workflow,
        WorkflowOptions $options
    ) {
        $this->rpc = $rpc;
        $this->marshaller = $marshaller;
        $this->workflow = $workflow;
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function signal(string $name, array $args = []): void
    {
        $this->assertWorkflowShouldBeStarted(__FUNCTION__);

        $this->rpc->call('temporal.SignalWorkflow', [
            'wid'         => $this->execution->id,
            'rid'         => $this->execution->runId,
            'signal_name' => $name,
            'args'        => $args,
        ]);
    }

    /**
     * @param string $method
     */
    private function assertWorkflowShouldBeStarted(string $method): void
    {
        if ($this->execution !== null) {
            return;
        }

        throw new \LogicException(\sprintf(self::ERROR_WORKFLOW_NOT_STARTED, $method));
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $name, array $args = [])
    {
        $this->assertWorkflowShouldBeStarted(__FUNCTION__);

        return $this->rpc->call('temporal.QueryWorkflow', [
            'wid'        => $this->execution->id,
            'rid'        => $this->execution->runId,
            'query_type' => $name,
            'args'       => $args,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function start(...$args): WorkflowExecution
    {
        $this->assertWorkflowShouldNotBeStarted();

        $result = $this->rpc->call('temporal.ExecuteWorkflow', [
            'name'    => $this->getWorkflowType(),
            'input'   => $args,
            'options' => $this->getOptionsArray(),
        ]);

        assert(\is_string($result['id'] ?? null));
        assert(\is_string($result['runId'] ?? null));

        return $this->execution = new WorkflowExecution($result['id'], $result['runId']);
    }

    /**
     * @return void
     */
    private function assertWorkflowShouldNotBeStarted(): void
    {
        if ($this->execution === null) {
            return;
        }

        throw new \LogicException(self::ERROR_WORKFLOW_START_DUPLICATION);
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflowType(): string
    {
        return $this->workflow;
    }

    /**
     * @return array
     */
    private function getOptionsArray(): array
    {
        return $this->marshaller->marshal(
            $this->getOptions()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): WorkflowOptions
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public function signalWithStart(string $signal, array $signalArgs = [], array $startArgs = []): WorkflowExecution
    {
        $this->assertWorkflowShouldNotBeStarted();

        $result = $this->rpc->call('temporal.SignalWithStartWorkflow', [
            'name'        => $this->getWorkflowType(),
            'input'       => $startArgs,
            'signal_name' => $signal,
            'signal_args' => $signalArgs,
            'options'     => $this->getOptionsArray(),
        ]);

        assert(\is_string($result['wid'] ?? null));
        assert(\is_string($result['rid'] ?? null));

        return $this->execution = new WorkflowExecution($result['wid'], $result['rid']);
    }

    /**
     * {@inheritDoc}
     */
    public function getExecution(): WorkflowExecution
    {
        $this->assertWorkflowShouldBeStarted(__FUNCTION__);

        return $this->execution;
    }

    /**
     * {@inheritDoc}
     */
    public function getResult($timeout = null)
    {
        $this->assertWorkflowShouldBeStarted(__FUNCTION__);

        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parseOrNull($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        return $this->rpc->call('temporal.GetWorkflow', [
            'wid'     => $this->execution->id,
            'rid'     => $this->execution->runId,
            'timeout' => $timeout ? $timeout->totalMicroseconds * 1000 : null,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(): void
    {
        $this->assertWorkflowShouldBeStarted(__FUNCTION__);

        $this->rpc->call('temporal.GetWorkflow', [
            'wid' => $this->execution->id,
            'rid' => $this->execution->runId,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(string $reason, array $details = []): void
    {
        $this->assertWorkflowShouldBeStarted(__FUNCTION__);

        $this->rpc->call('temporal.TerminateWorkflow', [
            'wid'     => $this->execution->id,
            'rid'     => $this->execution->runId,
            'reason'  => $reason,
            'details' => $details,
        ]);
    }
}
