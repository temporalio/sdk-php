<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal;

use JetBrains\PhpStorm\Immutable;
use Spiral\Attributes\ReaderInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Internal\Declaration\Prototype\ActivityCollection;
use Temporal\Internal\Declaration\Prototype\WorkflowCollection;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Workflow\ProcessCollection;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\WorkerFactory;

#[Immutable]
final class ServiceContainer
{
    /**
     * @var LoopInterface
     */
    #[Immutable]
    public LoopInterface $loop;

    /**
     * @var ClientInterface
     */
    #[Immutable]
    public ClientInterface $client;

    /**
     * @var ReaderInterface
     */
    #[Immutable]
    public ReaderInterface $reader;

    /**
     * @var EnvironmentInterface
     */
    #[Immutable]
    public EnvironmentInterface $env;

    /**
     * @var MarshallerInterface<array>
     */
    #[Immutable]
    public MarshallerInterface $marshaller;

    /**
     * @var RepositoryInterface<WorkflowPrototype>
     */
    #[Immutable]
    public RepositoryInterface $workflows;

    /**
     * @var ProcessCollection
     */
    #[Immutable]
    public ProcessCollection $running;

    /**
     * @var ActivityCollection
     */
    #[Immutable]
    public ActivityCollection $activities;

    /**
     * @var QueueInterface
     */
    #[Immutable]
    public QueueInterface $queue;

    /**
     * @var DataConverterInterface
     */
    #[Immutable]
    public DataConverterInterface $dataConverter;

    /**
     * @var WorkflowReader
     */
    #[Immutable]
    public WorkflowReader $workflowsReader;

    /**
     * @var ActivityReader
     */
    #[Immutable]
    public ActivityReader $activitiesReader;

    /**
     * @var ExceptionInterceptorInterface
     */
    public ExceptionInterceptorInterface $exceptionInterceptor;

    /**
     * @var PipelineProvider
     */
    public PipelineProvider $interceptorProvider;

    /**
     * @param LoopInterface $loop
     * @param EnvironmentInterface $env
     * @param ClientInterface $client
     * @param ReaderInterface $reader
     * @param QueueInterface $queue
     * @param MarshallerInterface<array> $marshaller
     * @param DataConverterInterface $dataConverter
     * @param ExceptionInterceptorInterface $exceptionInterceptor
     * @param PipelineProvider $interceptorProvider
     */
    public function __construct(
        LoopInterface $loop,
        EnvironmentInterface $env,
        ClientInterface $client,
        ReaderInterface $reader,
        QueueInterface $queue,
        MarshallerInterface $marshaller,
        DataConverterInterface $dataConverter,
        ExceptionInterceptorInterface $exceptionInterceptor,
        PipelineProvider $interceptorProvider,
    ) {
        $this->env = $env;
        $this->loop = $loop;
        $this->client = $client;
        $this->reader = $reader;
        $this->queue = $queue;
        $this->marshaller = $marshaller;
        $this->dataConverter = $dataConverter;
        $this->interceptorProvider = $interceptorProvider;

        $this->workflows = new WorkflowCollection();
        $this->activities = new ActivityCollection();
        $this->running = new ProcessCollection();

        $this->workflowsReader = new WorkflowReader($this->reader);
        $this->activitiesReader = new ActivityReader($this->reader);
        $this->exceptionInterceptor = $exceptionInterceptor;
    }

    public static function fromWorkerFactory(
        WorkerFactory|LoopInterface $worker,
        ExceptionInterceptorInterface $exceptionInterceptor,
        PipelineProvider $interceptorProvider,
    ): self {
        return new self(
            $worker,
            $worker->getEnvironment(),
            $worker->getClient(),
            $worker->getReader(),
            $worker->getQueue(),
            $worker->getMarshaller(),
            $worker->getDataConverter(),
            $exceptionInterceptor,
            $interceptorProvider,
        );
    }
}
