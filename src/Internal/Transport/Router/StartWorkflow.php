<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\Reader\Readers;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\ProcessCollection;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\WorkflowInfo;

final class StartWorkflow extends Route
{
    private const ERROR_NOT_FOUND = 'Workflow with the specified name "%s" was not registered';
    private const ERROR_ALREADY_RUNNING = 'Workflow "%s" with run id "%s" has been already started';

    private WorkflowInstantiator $instantiator;
    private EnvironmentInterface $environment;
    private MarshallerInterface $marshaller;
    private Readers $readers;
    private ClientInterface $client;
    private DataConverterInterface $dataConverter;
    private LoopInterface $loop;
    private QueueInterface $queue;
    private ExceptionInterceptor $exceptionInterceptor;
    private RepositoryInterface $workflows;
    private ProcessCollection $running;

    public function __construct(
        LoopInterface $loop,
        QueueInterface $queue,
        ExceptionInterceptor $exceptionInterceptor,
        EnvironmentInterface $environment,
        MarshallerInterface $marshaller,
        Readers $readers,
        ClientInterface $client,
        DataConverterInterface $dataConverter,
        RepositoryInterface $workflows,
        ProcessCollection $running
    ) {
        $this->instantiator = new WorkflowInstantiator();
        $this->environment = $environment;
        $this->marshaller = $marshaller;
        $this->readers = $readers;
        $this->client = $client;
        $this->dataConverter = $dataConverter;
        $this->loop = $loop;
        $this->queue = $queue;
        $this->exceptionInterceptor = $exceptionInterceptor;
        $this->workflows = $workflows;
        $this->running = $running;
    }

    /**
     * {@inheritDoc}
     * @throws \Throwable
     */
    public function handle(RequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $payloads = $request->getPayloads();
        $lastCompletionResult = null;

        if (($options['lastCompletion'] ?? 0) !== 0) {
            $offset = count($payloads) - ($options['lastCompletion'] ?? 0);

            $lastCompletionResult = EncodedValues::sliceValues($this->dataConverter, $payloads, $offset);
            $payloads = EncodedValues::sliceValues($this->dataConverter, $payloads, 0, $offset);
        }

        $input = $this->marshaller->unmarshal($options, new Input());
        $input->input = $payloads;

        $instance = $this->instantiator->instantiate($this->findWorkflowOrFail($input->info));

        $context = new WorkflowContext(
            $this->environment,
            $this->marshaller,
            $this->readers,
            $this->client,
            $instance,
            $input,
            $lastCompletionResult
        );

        $process = new Process(
            $this->loop,
            $this->queue,
            $context,
            $this->exceptionInterceptor
        );
        $this->running->add($process);
        $resolver->resolve(EncodedValues::fromValues([null]));

        $process->start($instance->getHandler(), $context->getInput());
    }

    /**
     * @param WorkflowInfo $info
     * @return WorkflowPrototype
     */
    private function findWorkflowOrFail(WorkflowInfo $info): WorkflowPrototype
    {
        $workflow = $this->workflows->find($info->type->name);

        if ($workflow === null) {
            throw new \OutOfRangeException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        if ($this->running->find($info->execution->getRunID()) !== null) {
            $message = \sprintf(self::ERROR_ALREADY_RUNNING, $info->type->name, $info->execution->getRunID());

            throw new \LogicException($message);
        }

        return $workflow;
    }
}
