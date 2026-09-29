<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Versioning\Routing;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\Uuid;
use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\Common\Versioning\VersioningOverride;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\App\Versioning\DeploymentRouting;
use Temporal\Tests\Acceptance\App\Versioning\SecondVersionAutoUpgradeWorkflow;
use Temporal\Tests\Acceptance\App\Versioning\SecondVersionOverrideWorkflow;
use Temporal\Tests\Acceptance\App\Versioning\SecondVersionPinnedWorkflow;
use Temporal\Tests\Acceptance\App\Versioning\SecondVersionRampWorkflow;
use Temporal\Tests\Acceptance\App\Versioning\SecondVersionWorker;
use Temporal\Tests\Acceptance\App\Versioning\SignalName;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowVersioningBehavior;

#[Worker(options: [RoutingTest::class, 'workerOptions'])]
class RoutingTest extends TestCase
{
    public const DEPLOYMENT_NAME = 'extra-versioning-routing';
    public const BUILD_ID_V1 = '1.0';
    public const BUILD_ID_V2 = '2.0';

    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()->withDeploymentOptions(
            WorkerDeploymentOptions::new()
                ->withUseVersioning(true)
                ->withVersion(WorkerDeploymentVersion::new(self::DEPLOYMENT_NAME, self::BUILD_ID_V1))
                ->withDefaultVersioningBehavior(VersioningBehavior::AutoUpgrade),
        );
    }

    #[Test]
    public function pinnedWorkflowStaysOnItsVersion(
        State $runtime,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $this->runRoutingScenario(
            $runtime,
            $client,
            $feature,
            SecondVersionPinnedWorkflow::class,
            SecondVersionPinnedWorkflow::WORKFLOW_TYPE,
            self::BUILD_ID_V1,
            static fn(DeploymentRouting $routing) => $routing->setCurrentVersion(self::BUILD_ID_V2),
            'prefix_v1',
        );
    }

    #[Test]
    public function autoUpgradeWorkflowMovesToTheCurrentVersion(
        State $runtime,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $this->runRoutingScenario(
            $runtime,
            $client,
            $feature,
            SecondVersionAutoUpgradeWorkflow::class,
            SecondVersionAutoUpgradeWorkflow::WORKFLOW_TYPE,
            self::BUILD_ID_V1,
            static fn(DeploymentRouting $routing) => $routing->setCurrentVersion(self::BUILD_ID_V2),
            'prefix_v2',
        );
    }

    #[Test]
    public function rampingVersionTakesOverAutoUpgradeWorkflow(
        State $runtime,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $this->runRoutingScenario(
            $runtime,
            $client,
            $feature,
            SecondVersionRampWorkflow::class,
            SecondVersionRampWorkflow::WORKFLOW_TYPE,
            self::BUILD_ID_V1,
            static fn(DeploymentRouting $routing) => $routing->setRampingVersion(self::BUILD_ID_V2, 100.0),
            'prefix_v2',
        );
    }

    #[Test]
    public function pinnedOverrideKeepsTheWorkflowOnTheOldVersion(
        State $runtime,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $this->runRoutingScenario(
            $runtime,
            $client,
            $feature,
            SecondVersionOverrideWorkflow::class,
            SecondVersionOverrideWorkflow::WORKFLOW_TYPE,
            self::BUILD_ID_V2,
            null,
            'prefix_v1',
            WorkflowOptions::new()->withVersioningOverride(
                VersioningOverride::pinned(
                    WorkerDeploymentVersion::new(self::DEPLOYMENT_NAME, self::BUILD_ID_V1),
                ),
            ),
        );
    }

    /**
     * @param class-string $secondVersionWorkflow
     * @param null|callable(DeploymentRouting): void $reroute
     */
    private function runRoutingScenario(
        State $runtime,
        WorkflowClientInterface $client,
        Feature $feature,
        string $secondVersionWorkflow,
        string $workflowType,
        string $currentBuildId,
        ?callable $reroute,
        string $expected,
        ?WorkflowOptions $options = null,
    ): void {
        $routing = new DeploymentRouting($client, $runtime->namespace, self::DEPLOYMENT_NAME);
        $second = new SecondVersionWorker(
            $runtime,
            $feature->taskQueue,
            self::DEPLOYMENT_NAME,
            self::BUILD_ID_V2,
            $secondVersionWorkflow,
        );
        $second->start();

        try {
            $routing->setCurrentVersion($currentBuildId);

            $stub = $this->startWorkflow($client, $feature, $workflowType, $options);
            $routing->awaitWorkflowRunning($stub->getExecution()->getID());

            if ($reroute !== null) {
                $reroute($routing);
            }

            $stub->signal(SignalName::START, 'prefix');

            self::assertSame($expected, $stub->getResult(timeout: 20));
        } finally {
            $second->stop();
        }
    }

    private function startWorkflow(
        WorkflowClientInterface $client,
        Feature $feature,
        string $workflowType,
        ?WorkflowOptions $options,
    ): WorkflowStubInterface {
        $stub = $client->newUntypedWorkflowStub(
            $workflowType,
            ($options ?? WorkflowOptions::new())
                ->withWorkflowId(Uuid::v4())
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowExecutionTimeout(60),
        );
        $client->start($stub);

        return $stub;
    }
}

#[WorkflowInterface]
class FirstVersionPinnedWorkflow
{
    use FirstVersionSignal;

    #[WorkflowMethod(name: SecondVersionPinnedWorkflow::WORKFLOW_TYPE)]
    #[WorkflowVersioningBehavior(VersioningBehavior::Pinned)]
    public function run()
    {
        return yield from $this->awaitValue();
    }
}

#[WorkflowInterface]
class FirstVersionAutoUpgradeWorkflow
{
    use FirstVersionSignal;

    #[WorkflowMethod(name: SecondVersionAutoUpgradeWorkflow::WORKFLOW_TYPE)]
    #[WorkflowVersioningBehavior(VersioningBehavior::AutoUpgrade)]
    public function run()
    {
        return yield from $this->awaitValue();
    }
}

#[WorkflowInterface]
class FirstVersionRampWorkflow
{
    use FirstVersionSignal;

    #[WorkflowMethod(name: SecondVersionRampWorkflow::WORKFLOW_TYPE)]
    #[WorkflowVersioningBehavior(VersioningBehavior::AutoUpgrade)]
    public function run()
    {
        return yield from $this->awaitValue();
    }
}

#[WorkflowInterface]
class FirstVersionOverrideWorkflow
{
    use FirstVersionSignal;

    #[WorkflowMethod(name: SecondVersionOverrideWorkflow::WORKFLOW_TYPE)]
    #[WorkflowVersioningBehavior(VersioningBehavior::AutoUpgrade)]
    public function run()
    {
        return yield from $this->awaitValue();
    }
}

trait FirstVersionSignal
{
    private ?string $value = null;

    #[SignalMethod(name: SignalName::START)]
    public function start(string $value): void
    {
        $this->value = $value;
    }

    private function awaitValue(): \Generator
    {
        yield Workflow::await(fn(): bool => $this->value !== null);

        return $this->value . '_v1';
    }
}
