<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Worker;

use Coresdk\Activity_task\ActivityTask;
use Coresdk\ActivityTaskCompletion;
use Coresdk\Workflow_activation\WorkflowActivation;
use Coresdk\Workflow_completion\WorkflowActivationCompletion;
use PHPUnit\Framework\TestCase;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Workflowservice\V1\ListWorkersRequest;
use Temporal\Api\Workflowservice\V1\PauseActivityRequest;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;
use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Internal\Bridge\CoreEnvironment;
use Temporal\Tests\Core\DevServer;
use Temporal\Tests\Unit\Client\Stub\LoggerSpy;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

final class CoreWorkerFactoryTestCase extends TestCase
{
    private const SAFETY_TIMEOUT_SECONDS = 60;

    public static string $address = '';
    public static bool $stopInWorkflow = false;

    /** @var list<class-string<\Throwable>> */
    public static array $heartbeatErrors = [];

    public function testOnlyActivityTasksCanBeTheRpcConnection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CoreWorkerFactory::create(rpc: $this->createStub(RPCConnectionInterface::class));
    }

    public function testHostConnectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CoreWorkerFactory::create()->run($this->createStub(HostConnectionInterface::class));
    }

    public function testChildRoleWithoutWorkReturns(): void
    {
        $logger = new LoggerSpy();
        $_SERVER[CoreEnvironment::ROLE] = 'activity';
        try {
            $code = CoreWorkerFactory::create(logger: $logger)->run();
        } finally {
            unset($_SERVER[CoreEnvironment::ROLE]);
        }

        self::assertSame(0, $code);
        self::assertSame('No task queue needs a activity process, exiting', $logger->records[0]['message']);
    }

    public function testSingleProcessWithoutWorkReturns(): void
    {
        $logger = new LoggerSpy();

        $code = CoreWorkerFactory::create(logger: $logger)->run();

        self::assertSame(0, $code);
        self::assertSame('No task queue needs a all process, exiting', $logger->records[0]['message']);
    }

    public function testWorkerRunsWorkflowsAndActivities(): void
    {
        $queue = self::startWorkflow();
        $factory = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        $factory->newWorker($queue)
            ->registerWorkflowTypes(CoreHeartbeatWorkflow::class)
            ->registerActivityImplementations(new CoreHeartbeatActivity());
        $wire = [];
        $factory->observeWire(static function (string $type, string $bytes) use (&$wire): void {
            (new $type())->mergeFromString($bytes);
            $wire[$type] = true;
        });

        self::assertSame(0, self::runWithTimeout($factory));
        self::assertSame([ActivityPausedException::class], self::$heartbeatErrors);
        self::assertEqualsCanonicalizing(
            [WorkflowActivation::class, WorkflowActivationCompletion::class, ActivityTask::class, ActivityTaskCompletion::class],
            \array_keys($wire),
        );
    }

    public function testResourceBasedTunerRunsWorkflowsAndActivities(): void
    {
        $queue = self::startWorkflow();
        $tuner = [CoreEnvironment::TUNER_TARGET_MEMORY_USAGE => '0.9', CoreEnvironment::TUNER_TARGET_CPU_USAGE => '0.9'];
        $_SERVER = $tuner + $_SERVER;
        try {
            $factory = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        } finally {
            foreach (\array_keys($tuner) as $name) {
                unset($_SERVER[$name]);
            }
        }
        $factory->newWorker($queue)
            ->registerWorkflowTypes(CoreHeartbeatWorkflow::class)
            ->registerActivityImplementations(new CoreHeartbeatActivity());

        self::assertSame(0, self::runWithTimeout($factory));
        self::assertSame([ActivityPausedException::class], self::$heartbeatErrors);
        self::assertSame(['ResourceBased', 'ResourceBased', 'Fixed'], self::slotSupplierKinds($queue));
    }

    public function testFailedWorkerCreationReleasesTheEarlierTaskQueues(): void
    {
        $queue = self::startWorkflow();
        $failing = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        $failing->newWorker($queue)->registerWorkflowTypes(CoreHeartbeatWorkflow::class);
        $failing->newWorker($queue . '-invalid', WorkerOptions::new()->withDeploymentOptions(WorkerDeploymentOptions::new()
            ->withUseVersioning(false)
            ->withVersion('core.invalid')
            ->withDefaultVersioningBehavior(VersioningBehavior::Pinned)))
            ->registerWorkflowTypes(CoreHeartbeatWorkflow::class);
        try {
            $failing->run();
            self::fail('The invalid deployment options must fail the worker creation');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('default_versioning_behavior', $e->getMessage());
        }

        $factory = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        $factory->newWorker($queue)
            ->registerWorkflowTypes(CoreHeartbeatWorkflow::class)
            ->registerActivityImplementations(new CoreHeartbeatActivity());

        self::assertSame(0, self::runWithTimeout($factory));
    }

    public function testActivityProcessRunsTheActivitiesOfTheWorkflowProcess(): void
    {
        $queue = self::startWorkflow();
        $workflows = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        $workflows->newWorker($queue, WorkerOptions::new()->withLocalActivityWorkerOnly(true))->registerWorkflowTypes(CoreHeartbeatWorkflow::class);
        self::$stopInWorkflow = true;
        try {
            self::assertSame(0, self::runWithTimeout($workflows));
        } finally {
            self::$stopInWorkflow = false;
        }

        $activities = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        $activities->newWorker($queue)->registerActivityImplementations(new CoreHeartbeatActivity());

        $_SERVER[CoreEnvironment::ROLE] = 'activity';
        try {
            self::assertSame(0, self::runWithTimeout($activities));
        } finally {
            unset($_SERVER[CoreEnvironment::ROLE]);
        }
        self::assertSame([ActivityPausedException::class], self::$heartbeatErrors);
    }

    public function testUpdatedApiKeyIsReadAgainWhileTheWorkerRuns(): void
    {
        $queue = self::startWorkflow();
        $factory = CoreWorkerFactory::create(address: self::$address, logger: new LoggerSpy());
        $factory->newWorker($queue, WorkerOptions::new()->withLocalActivityWorkerOnly(true))->registerWorkflowTypes(CoreHeartbeatWorkflow::class);
        $key = new class implements \Stringable {
            public int $reads = 0;

            public function __toString(): string
            {
                return 'key-' . ++$this->reads;
            }
        };
        $factory->updateApiKey($key);
        self::$stopInWorkflow = true;
        try {
            self::assertSame(0, self::runWithTimeout($factory));
        } finally {
            self::$stopInWorkflow = false;
        }

        self::assertGreaterThan(2, $key->reads);
    }

    private static function startWorkflow(): string
    {
        self::$address = DevServer::address();
        self::$heartbeatErrors = [];
        $queue = \uniqid('core-worker-', true);
        $client = WorkflowClient::create(ServiceClient::create(self::$address));
        $client->start($client->newUntypedWorkflowStub('CoreHeartbeatWorkflow', WorkflowOptions::new()->withTaskQueue($queue)));

        return $queue;
    }

    /**
     * @return list<string>
     */
    private static function slotSupplierKinds(string $queue): array
    {
        $workers = ServiceClient::create(self::$address)->ListWorkers(new ListWorkersRequest(['namespace' => 'default']));
        foreach ($workers->getWorkersInfo() as $info) {
            $heartbeat = $info->getWorkerHeartbeat();
            if ($heartbeat?->getTaskQueue() === $queue) {
                return [
                    (string) $heartbeat->getWorkflowTaskSlotsInfo()?->getSlotSupplierKind(),
                    (string) $heartbeat->getActivityTaskSlotsInfo()?->getSlotSupplierKind(),
                    (string) $heartbeat->getLocalActivitySlotsInfo()?->getSlotSupplierKind(),
                ];
            }
        }

        return [];
    }

    private static function runWithTimeout(CoreWorkerFactory $factory): int
    {
        \pcntl_signal(\SIGALRM, static fn() => \posix_kill(\getmypid(), \SIGTERM));
        \pcntl_alarm(self::SAFETY_TIMEOUT_SECONDS);
        try {
            return $factory->run();
        } finally {
            \pcntl_alarm(0);
            \pcntl_signal(\SIGALRM, \SIG_DFL);
        }
    }
}

#[WorkflowInterface]
final class CoreHeartbeatWorkflow
{
    #[WorkflowMethod(name: 'CoreHeartbeatWorkflow')]
    public function run(): \Generator
    {
        if (CoreWorkerFactoryTestCase::$stopInWorkflow) {
            \posix_kill(\getmypid(), \SIGTERM);
        }

        return yield Workflow::newActivityStub(CoreHeartbeatActivity::class, ActivityOptions::new()
            ->withStartToCloseTimeout(30)
            ->withHeartbeatTimeout(2)
            ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)))->beat();
    }
}

#[ActivityInterface(prefix: 'CoreHeartbeat.')]
final class CoreHeartbeatActivity
{
    private const HEARTBEATS = 100;
    private const HEARTBEAT_INTERVAL_US = 100_000;

    #[ActivityMethod]
    public function beat(): string
    {
        $info = Activity::getInfo();
        ServiceClient::create(CoreWorkerFactoryTestCase::$address)->PauseActivity(new PauseActivityRequest([
            'namespace' => $info->workflowNamespace,
            'execution' => new WorkflowExecution(['workflow_id' => $info->workflowExecution->getID()]),
            'id' => $info->id,
        ]));
        try {
            for ($i = 0; $i < self::HEARTBEATS; ++$i) {
                Activity::heartbeat($i);
                \usleep(self::HEARTBEAT_INTERVAL_US);
            }
        } catch (\Throwable $e) {
            CoreWorkerFactoryTestCase::$heartbeatErrors[] = $e::class;
            throw $e;
        } finally {
            \posix_kill(\getmypid(), \SIGTERM);
        }

        return 'not paused';
    }
}
