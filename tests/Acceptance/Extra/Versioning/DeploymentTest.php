<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Versioning\Deployment;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\Uuid;
use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\Common\Versioning\VersioningOverride;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowVersioningBehavior;

#[Worker(options: [WorkerFactory::class, 'options'])]
class DeploymentTest extends TestCase
{
    #[Test]
    public function defaultBehaviorAuto(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $behavior = self::executeWorkflow(
            $starter,
            $client,
            $feature,
            /** @see DefaultWorkflow */
            'Extra_Versioning_Deployment_Default',
            WorkflowOptions::new(),
        );
        self::assertSame(VersioningBehavior::AutoUpgrade, $behavior);
    }

    #[Test]
    public function customBehaviorPinned(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $id = Uuid::v4();
        self::executeWorkflow(
            $starter,
            $client,
            $feature,
            /** @see PinnedWorkflow */
            'Extra_Versioning_Deployment_Pinned',
            WorkflowOptions::new()->withWorkflowId($id),
            postAction: static function (VersioningBehavior $behavior) use ($client, $id): void {
                # Check worker registration
                self::assertSame(VersioningBehavior::Pinned, $behavior);

                # Check Override from Search Attributes
                $sa = $client->newUntypedRunningWorkflowStub(
                    workflowID: $id,
                    workflowType: 'Extra_Versioning_Deployment_Pinned',
                )->describe()->info->searchAttributes->getValues();
                self::assertSame('Pinned', $sa['TemporalWorkflowVersioningBehavior']);
                self::assertSame('foo:baz', $sa['TemporalWorkerDeploymentVersion']);
            },
        );
    }

    #[Test]
    public function versionBehaviorOverrideAutoUpgrade(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $id = Uuid::v4();
        self::executeWorkflow(
            $starter,
            $client,
            $feature,
            /** @see PinnedWorkflow */
            'Extra_Versioning_Deployment_Pinned',
            WorkflowOptions::new()->withWorkflowId($id)->withVersioningOverride(VersioningOverride::autoUpgrade()),
            postAction: static function (VersioningBehavior $behavior) use ($client, $id): void {
                # Check worker registration
                self::assertSame(VersioningBehavior::Pinned, $behavior);

                # Check Override from Search Attributes
                $sa = $client->newUntypedRunningWorkflowStub(
                    workflowID: $id,
                    workflowType: 'Extra_Versioning_Deployment_Pinned',
                )->describe()->info->searchAttributes->getValues();
                self::assertSame('AutoUpgrade', $sa['TemporalWorkflowVersioningBehavior']);
                self::assertSame('foo:baz', $sa['TemporalWorkerDeploymentVersion']);
            },
        );
    }

    #[Test]
    public function versionBehaviorOverridePinned(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $behavior = self::executeWorkflow(
            $starter,
            $client,
            $feature,
            /** @see PinnedWorkflow */
            'Extra_Versioning_Deployment_Default',
            WorkflowOptions::new()->withVersioningOverride(VersioningOverride::pinned(
                version: WorkerDeploymentVersion::new(
                    deploymentName: WorkerFactory::DEPLOYMENT_NAME,
                    buildId: WorkerFactory::BUILD_ID,
                ),
            )),
        );

        # Check worker registration
        self::assertSame(VersioningBehavior::AutoUpgrade, $behavior);
    }

    /**
     * @param null|callable(VersioningBehavior): void $postAction
     */
    private static function executeWorkflow(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
        string $workflowType,
        WorkflowOptions $options,
        ?callable $postAction = null,
    ): ?VersioningBehavior {
        WorkerFactory::setCurrentDeployment($starter);

        try {
            # Create a Workflow stub with an execution timeout 12 seconds
            $stub = $client
                ->withTimeout(10)
                ->newUntypedWorkflowStub(
                    /** @see PinnedWorkflow */
                    $workflowType,
                    $options
                        ->withTaskQueue($feature->taskQueue)
                        ->withWorkflowExecutionTimeout(20),
                );

            # Start the Workflow
            $client->start($stub);

            # Wait for the Workflow to complete
            $stub->getResult(timeout: 5);

            # Check the Workflow History
            $behavior = null;
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->hasWorkflowTaskCompletedEventAttributes()) {
                    $version = $event->getWorkflowTaskCompletedEventAttributes()?->getDeploymentVersion();
                    self::assertNotNull($version);
                    self::assertSame(WorkerFactory::DEPLOYMENT_NAME, $version->getDeploymentName());
                    self::assertSame(WorkerFactory::BUILD_ID, $version->getBuildId());

                    $behavior = VersioningBehavior::tryFrom(
                        $event->getWorkflowTaskCompletedEventAttributes()?->getVersioningBehavior(),
                    );
                    break;
                }
            }
            $behavior ?? throw new \RuntimeException(
                'The WorkflowTaskCompletedEventAttributes not found in the Workflow history.',
            );

            $postAction === null or $postAction($behavior);
            return $behavior;
        } finally {
            $starter->stop() and $starter->start();
            sleep(5);
        }
    }
}

class WorkerFactory
{
    public const DEPLOYMENT_NAME = 'foo';
    public const BUILD_ID = 'baz';

    public static function options(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withDeploymentOptions(
                WorkerDeploymentOptions::new()
                    ->withUseVersioning(true)
                    ->withVersion(WorkerDeploymentVersion::new(self::DEPLOYMENT_NAME, self::BUILD_ID))
                    ->withDefaultVersioningBehavior(VersioningBehavior::AutoUpgrade),
            );
    }

    public static function setCurrentDeployment(TemporalStarter $starter): void
    {
        $starter->executeTemporalCommand([
            'worker',
            'deployment',
            'set-current-version',
            '--deployment-name', WorkerFactory::DEPLOYMENT_NAME,
            '--build-id', WorkerFactory::BUILD_ID,
            '--yes',
        ], timeout: 5);
    }
}

#[WorkflowInterface]
class DefaultWorkflow
{
    #[WorkflowMethod(name: "Extra_Versioning_Deployment_Default")]
    public function handle()
    {
        return 'default';
    }
}

#[WorkflowInterface]
class PinnedWorkflow
{
    #[WorkflowMethod(name: "Extra_Versioning_Deployment_Pinned")]
    #[WorkflowVersioningBehavior(VersioningBehavior::Pinned)]
    public function handle()
    {
        return 'pinned';
    }
}
