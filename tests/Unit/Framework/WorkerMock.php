<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework;

use Closure;
use PHPUnit\Framework\Exception;
use React\Promise\PromiseInterface;
use Temporal\Common\Uuid;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Repository\Identifiable;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Tests\Unit\Framework\Expectation\WorkflowResult;
use Temporal\Tests\Unit\Framework\Expectation\ActivityCall;
use Temporal\Tests\Unit\Framework\Expectation\Timer;
use Temporal\Tests\Unit\Framework\Requests\InvokeSignal;
use Temporal\Tests\Unit\Framework\Requests\StartWorkflow;
use Temporal\Tests\Unit\Framework\Server\CommandHandler\CommandHandlerFactory;
use Temporal\Tests\Unit\Framework\Server\ServerMock;
use Temporal\Worker\DispatcherInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Throwable;

use function get_class;

/**
 * @internal
 */
final class WorkerMock implements Identifiable, WorkerInterface, DispatcherInterface
{
    private string $name;
    private WorkerOptions $options;
    private ServiceContainer $services;
    private RouterInterface $router;
    private ServerMock $server;

    /**
     * Contains currently executing Workflow
     * <string $workflowClass => string $uuid>
     */
    private array $execution = [];

    public function __construct(
        string $taskQueue,
        WorkerOptions $options,
        ServiceContainer $serviceContainer
    ) {
        $this->name = $taskQueue;
        $this->options = $options;
        $this->services = $serviceContainer;
        $this->router = $this->createRouter();
        $this->server = new ServerMock(CommandHandlerFactory::create());
    }

    private function createRouter(): RouterInterface
    {
        $router = new Router();
        $router->add(new Router\StartWorkflow($this->services));
        $router->add(new Router\InvokeActivity($this->services, Goridge::create()));
        $router->add(new Router\DestroyWorkflow($this->services->running));
        $router->add(new Router\InvokeSignal($this->services->running, $this->services->loop));

        return $router;
    }

    public function runWorkflow(string $workflowCLass, ...$args): void
    {
        $runId = Uuid::v4();
        $this->execution[$workflowCLass] = $runId;
        $this->server->addCommand(new StartWorkflow($runId, $workflowCLass, ...$args));
    }

    public function sendSignal(string $workflow, string $name, ...$args): void
    {
        $workflowRunId = $this->execution[$workflow] ?? null;
        if ($workflowRunId === null) {
            throw new \LogicException("Cannot send signal to $workflow, it is not running.");

        }
        $this->server->addCommand(new InvokeSignal($workflowRunId, $name, ...$args));
    }

    public function waitBatch(): ?CommandBatchMock
    {
        if ($this->server->hasEmptyQueue()) {
            return null;
        }

        return $this->server->getBatch();
    }

    public function send(QueueInterface $commands): void
    {
        foreach ($commands as $command) {
            $result = $this->server->handleCommand($command);
            if ($result !== null) {
                $this->server->addCommand($result);
            }
        }
    }

    /**
     * @throws Throwable
     */
    public function error(Throwable $error): void
    {
        if ($error instanceof Exception) {
            throw $error;
        }
    }

    public function getID(): string
    {
        return $this->name;
    }

    public function getOptions(): WorkerOptions
    {
        return $this->options;
    }

    public function registerWorkflowTypes(string ...$class): WorkerInterface
    {
        foreach ($class as $workflow) {
            $proto = $this->services->workflowsReader->fromClass($workflow);
            $this->services->workflows->add($proto, false);
        }

        return $this;
    }

    public function registerWorkflowObject($object): self
    {
        $proto = $this->services->workflowsReader->fromObject($object);
        $this->services->workflows->add($proto, false);

        return $this;
    }

    public function getWorkflows(): iterable
    {
        return $this->services->workflows;
    }

    public function registerActivityImplementations(object ...$activity): WorkerInterface
    {
        foreach ($activity as $act) {
            $this->registerActivity(\get_class($act), fn() => $act);
        }

        return $this;
    }


    public function registerActivity(string $type, callable $factory = null): WorkerInterface
    {
        foreach ($this->services->activitiesReader->fromClass($type) as $proto) {
            /** @var ActivityPrototype $proto */
            if ($factory !== null) {
                $proto = $proto->withFactory($factory);
            }
            $this->services->activities->add($proto, false);
        }

        return $this;
    }

    public function getActivities(): iterable
    {
        return $this->services->activities;
    }

    public function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        return $this->router->dispatch($request, $headers);
    }

    public function expectActivityCall(string $class, string $method, ...$returnValues): void
    {
        $this->server->expect(new ActivityCall($class, $method, $returnValues));
    }

    public function expectTimer(int $seconds): void
    {
        $this->server->expect(new Timer($seconds));
    }

    public function assertWorkflowReturns($value): void
    {
        $this->server->expect(new WorkflowResult($value));
    }

    public function complete(): void
    {
        $this->server->checkWaitingExpectations();
        $this->execution = [];
    }

    public function registerActivityFinalizer(Closure $finalizer): WorkerInterface
    {
        $this->services->activities->addFinalizer($finalizer);

        return $this;
    }
}
