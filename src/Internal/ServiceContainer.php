<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal;

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

final class ServiceContainer
{
    /** @var RepositoryInterface<WorkflowPrototype> */
    public readonly RepositoryInterface $workflows;

    public readonly ProcessCollection $running;
    public readonly ActivityCollection $activities;
    public readonly WorkflowReader $workflowsReader;
    public readonly ActivityReader $activitiesReader;

    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        public readonly LoopInterface $loop,
        public readonly EnvironmentInterface $env,
        public readonly ClientInterface $client,
        public readonly ReaderInterface $reader,
        public readonly QueueInterface $queue,
        public readonly MarshallerInterface $marshaller,
        public readonly DataConverterInterface $dataConverter,
        public readonly ExceptionInterceptorInterface $exceptionInterceptor,
        public readonly PipelineProvider $interceptorProvider,
    ) {
        $this->workflows = new WorkflowCollection();
        $this->activities = new ActivityCollection();
        $this->running = new ProcessCollection();
        $this->workflowsReader = new WorkflowReader($this->reader);
        $this->activitiesReader = new ActivityReader($this->reader);
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
