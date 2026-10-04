<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Temporal\Internal\Bridge\Bridge;
use Carbon\CarbonInterval;
use Psr\Log\LoggerInterface;
use Temporal\Api\History\V1\History;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Worker\ServiceCredentials;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkflowPanicPolicy;
use Temporal\WorkerFactory;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class CoreWorkerFactory extends WorkerFactory
{
    private const STOP_GRACE_SECONDS = 10.0;

    private CoreOptions $options;
    private CoreWorkerConfig $config;
    private ActivityTasks $activityTasks;
    private LoggerInterface $logger;

    public static function create(
        ?DataConverterInterface $converter = null,
        ?RPCConnectionInterface $rpc = null,
        ?ServiceCredentials $credentials = null,
        ?PluginRegistry $pluginRegistry = null,
        ?WorkflowClient $client = null,
        ?string $address = null,
        ?string $namespace = null,
        ?int $workflowProcesses = null,
        ?int $activityProcesses = null,
        ?LoggerInterface $logger = null,
    ): static {
        $converter ??= DataConverter::createDefault();
        $rpc ??= new ActivityTasks();
        if (!$rpc instanceof ActivityTasks) {
            throw new \InvalidArgumentException(\sprintf('The sdk-core transport needs %s as the RPC connection', ActivityTasks::class));
        }
        /** @psalm-suppress UnsafeInstantiation */
        $factory = new static($converter, $rpc, $credentials, $pluginRegistry, $client);
        $factory->activityTasks = $rpc;
        $factory->options = CoreOptions::create($address, $namespace, $credentials, $workflowProcesses, $activityProcesses);
        $factory->config = new CoreWorkerConfig($factory->options, $factory->marshaller);
        $factory->logger = $logger ?? new StderrLogger();

        return $factory;
    }

    public function run(?HostConnectionInterface $host = null): int
    {
        if ($host !== null) {
            throw new \InvalidArgumentException('The sdk-core transport does not use a host connection');
        }

        $role = ChildProcesses::currentRole();
        if ($role !== null) {
            exit($this->serve($role, true));
        }

        $workflowProcesses = $this->options->workflowProcesses;
        $activityProcesses = $this->options->activityProcesses;
        if ($workflowProcesses + $activityProcesses <= 1) {
            return $this->serve($activityProcesses === 0 ? CoreRole::All : CoreRole::Activity, false);
        }

        $workflowRole = $activityProcesses === 0 ? CoreRole::All : CoreRole::Workflow;
        $roles = [
            ...\array_fill(0, $this->config->hasWork($this->queues, $workflowRole) ? $workflowProcesses : 0, $workflowRole),
            ...\array_fill(0, $this->config->hasWork($this->queues, CoreRole::Activity) ? $activityProcesses : 0, CoreRole::Activity),
        ];
        $children = new ChildProcesses(fn(CoreRole $role): int => $this->serve($role, true), $this->logger);
        $supervisor = new Supervisor($children->start(...), $children->release(...), $this->logger, $this->stopTimeoutSeconds());

        return $supervisor->run($roles);
    }

    public function replay(History $history, string $workflowId): void
    {
        $events = $history->getEvents();
        if (\count($events) === 0) {
            throw new \InvalidArgumentException('The history has no events');
        }
        $taskQueue = (string) $events[0]->getWorkflowExecutionStartedEventAttributes()?->getTaskQueue()?->getName();
        $worker = $this->findWorkerByTaskQueue($taskQueue);

        $bridge = Bridge::shared();
        $bridge->useLogger($this->logger);
        (new CoreReplayer($bridge))->replay(
            $history,
            $workflowId,
            ['nondeterminism_fails_workflow' => false] + $this->config->build($worker, CoreRole::Workflow),
            $this->activations($worker, $this->dispatch(...)),
        );
    }

    private function serve(CoreRole $role, bool $supervised): int
    {
        $bridge = Bridge::shared();
        $bridge->useLogger($this->logger);
        $loop = new CoreWorkerLoop(
            $bridge,
            $this->options,
            $this->config,
            $this->activityTasks,
            $this->dispatch(...),
            $this->activations(...),
            $this->converter,
            $this->logger,
        );

        return Pipeline::prepare($this->pluginRegistry->getPlugins(WorkerPluginInterface::class))
            ->with(fn(): int => $loop->serve($this->queues, $role, $supervised), 'run')($this);
    }

    private function stopTimeoutSeconds(): float
    {
        $timeouts = [0.0];
        foreach ($this->queues as $worker) {
            $timeout = $worker->getOptions()->workerStopTimeout;
            if ($timeout !== null) {
                $timeouts[] = CarbonInterval::instance($timeout)->totalSeconds;
            }
        }

        return \max($timeouts) + self::STOP_GRACE_SECONDS;
    }

    private function activations(WorkerInterface $worker, \Closure $dispatch): WorkflowActivations
    {
        $behaviors = [];
        foreach ($worker->getWorkflows() as $workflow) {
            $behaviors[$workflow->getID()] = $workflow->getVersioningBehavior()->value;
        }

        return new WorkflowActivations(
            $this->converter,
            $dispatch,
            $this->options->namespace,
            (string) $worker->getID(),
            $behaviors,
            $worker->getOptions()->workflowPanicPolicy === WorkflowPanicPolicy::FailWorkflow,
        );
    }

    /**
     * @param list<CommandInterface> $commands
     * @return list<CommandInterface>
     */
    private function dispatch(array $commands, array $headers): array
    {
        /** @var list<ServerRequestInterface|ServerResponseInterface> $commands */
        $this->dispatchCommands($commands, $headers);

        return \iterator_to_array($this->responses, false);
    }
}
