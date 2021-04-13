<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Doctrine\Common\Annotations\Reader;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\Composite;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Client\ActivityCompletionClient;
use Temporal\Internal\Client\WorkflowRun;
use Temporal\Internal\Client\WorkflowStarter;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Client\WorkflowProxy;
use Temporal\Internal\Client\WorkflowStub;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;
use Temporal\Workflow\WorkflowStub as WorkflowStubConverter;

class WorkflowClient implements WorkflowClientInterface
{
    private const ERROR_WORKFLOW_START_DUPLICATION =
        'Cannot reuse a stub instance to start more than one workflow execution. ' .
        'The stub points to already started execution. If you are trying to wait ' .
        'for a workflow completion either change WorkflowIdReusePolicy from ' .
        'AllowDuplicate or use WorkflowStub.getResult';

    private ServiceClientInterface $client;
    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;
    private WorkflowStarter $starter;
    private WorkflowReader $reader;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions|null $options
     * @param DataConverterInterface|null $converter
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ) {
        $this->client = $serviceClient;
        $this->clientOptions = $options ?? new ClientOptions();
        $this->converter = $converter ?? DataConverter::createDefault();
        $this->starter = new WorkflowStarter($serviceClient, $this->converter, $this->clientOptions);
        $this->reader = new WorkflowReader($this->createReader());
    }

    /**
     * @return ReaderInterface
     */
    private function createReader(): ReaderInterface
    {
        if (\interface_exists(Reader::class)) {
            return new SelectiveReader([new AnnotationReader(), new AttributeReader()]);
        }

        return new AttributeReader();
    }

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions|null $options
     * @param DataConverterInterface|null $converter
     * @return static
     */
    public static function create(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ): self {
        return new self($serviceClient, $options, $converter);
    }

    /**
     * @return ServiceClientInterface
     */
    public function getServiceClient(): ServiceClientInterface
    {
        return $this->client;
    }

    /**
     * Starts workflow in async mode. Returns WorkflowRun object which can be used to wait for the execution result.
     * WorkflowRun objects created by typed workflow stubs will attempt to type the execution result as well.
     *
     * @param object|WorkflowStubInterface $workflow
     * @param mixed ...$args
     * @return WorkflowRunInterface
     */
    public function start($workflow, ...$args): WorkflowRunInterface
    {
        $workflowStub = WorkflowStubConverter::fromWorkflow($workflow);

        $returnType = null;
        if ($workflow instanceof WorkflowProxy) {
            $returnType = $workflow->__getReturnType();
        }

        if ($workflowStub->getWorkflowType() === null) {
            throw new InvalidArgumentException(
                \sprintf('Unable to start untyped workflow without given workflowType')
            );
        }

        if ($workflowStub->hasExecution()) {
            throw new InvalidArgumentException(self::ERROR_WORKFLOW_START_DUPLICATION);
        }

        $execution = $this->starter->start(
            $workflowStub->getWorkflowType(),
            $workflowStub->getOptions() ?? WorkflowOptions::new(),
            $args
        );

        $workflowStub->setExecution($execution);

        return new WorkflowRun($workflowStub, $returnType);
    }

    /**
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
    ): WorkflowRunInterface {
        $workflowStub = WorkflowStubConverter::fromWorkflow($workflow);

        $returnType = null;
        if ($workflow instanceof WorkflowProxy) {
            $returnType = $workflow->__getReturnType();
        }

        if ($workflowStub->getWorkflowType() === null) {
            throw new InvalidArgumentException(
                \sprintf('Unable to start untyped workflow without given workflowType')
            );
        }

        if ($workflowStub->hasExecution()) {
            throw new InvalidArgumentException(self::ERROR_WORKFLOW_START_DUPLICATION);
        }

        $execution = $this->starter->signalWithStart(
            $workflowStub->getWorkflowType(),
            $workflowStub->getOptions() ?? WorkflowOptions::new(),
            $signal,
            $signalArgs,
            $startArgs
        );

        $workflowStub->setExecution($execution);

        return new WorkflowRun($workflowStub, $returnType);
    }

    /**
     * {@inheritDoc}
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): object
    {
        $workflow = $this->reader->fromClass($class);

        return new WorkflowProxy(
            $this,
            $this->newUntypedWorkflowStub($workflow->getID(), $options),
            $workflow
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedWorkflowStub(string $workflowType, WorkflowOptions $options = null): WorkflowStubInterface
    {
        $options ??= new WorkflowOptions();

        return new WorkflowStub(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $workflowType,
            $options
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newRunningWorkflowStub(string $class, string $workflowID, ?string $runID = null): object
    {
        $workflow = $this->reader->fromClass($class);

        return new WorkflowProxy(
            $this,
            $this->newUntypedRunningWorkflowStub($workflowID, $runID, $workflow->getID()),
            $workflow
        );
    }

    /**
     * {@inheritDoc}
     */
    public function newUntypedRunningWorkflowStub(
        string $workflowID,
        ?string $runID = null,
        ?string $workflowType = null
    ): WorkflowStubInterface {
        $untyped = new WorkflowStub($this->client, $this->clientOptions, $this->converter, $workflowType);
        $untyped->setExecution(new WorkflowExecution($workflowID, $runID));

        return $untyped;
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityCompletionClient(): ActivityCompletionClientInterface
    {
        return new ActivityCompletionClient($this->client, $this->clientOptions, $this->converter);
    }
}
