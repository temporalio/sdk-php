<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Versioning\Deployment;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\VersioningBehavior;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\Versioning\WorkerDeploymentOptions;
use Temporal\Worker\Versioning\WorkerDeploymentVersion;
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
        WorkerFactory::setCurrentDeployment($starter);

        try {
            # Create a Workflow stub with an execution timeout 12 seconds
            $stub = $client
                ->withTimeout(10)
                ->newUntypedWorkflowStub(
                    /** @see PinnedWorkflow */
                    'Extra_Versioning_Deployment_Default',
                    WorkflowOptions::new()
                        ->withTaskQueue($feature->taskQueue)
                        ->withWorkflowExecutionTimeout(20),
                );

            # Start the Workflow
            $client->start($stub);

            # Check the result
            $result = $stub->getResult(timeout: 5);
            self::assertSame('default', $result);

            # Check the Workflow History
            $version = null;
            $behavior = null;
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->hasWorkflowTaskCompletedEventAttributes()) {
                    $version = $event->getWorkflowTaskCompletedEventAttributes()?->getDeploymentVersion();
                    $behavior = $event->getWorkflowTaskCompletedEventAttributes()?->getVersioningBehavior();
                    break;
                }
            }

            self::assertNotNull($version);
            self::assertSame(WorkerFactory::DEPLOYMENT_NAME, $version->getDeploymentName());
            self::assertSame(WorkerFactory::BUILD_ID, $version->getBuildId());
            self::assertSame(VersioningBehavior::AutoUpgrade->value, $behavior);
        } finally {
            $starter->stop() and $starter->start();
        }
    }

    #[Test]
    public function customBehaviorPinned(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        WorkerFactory::setCurrentDeployment($starter);

        try {
            # Create a Workflow stub with an execution timeout 12 seconds
            $stub = $client
                ->withTimeout(10)
                ->newUntypedWorkflowStub(
                    /** @see DefaultWorkflow */
                    'Extra_Versioning_Deployment_Pinned',
                    WorkflowOptions::new()
                        ->withTaskQueue($feature->taskQueue)
                        ->withWorkflowExecutionTimeout(20),
                );

            # Start the Workflow
            $client->start($stub);

            # Check the result
            $result = $stub->getResult(timeout: 5);
            self::assertSame('pinned', $result);

            # Check the Workflow History
            $version = null;
            $behavior = null;
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->hasWorkflowTaskCompletedEventAttributes()) {
                    $version = $event->getWorkflowTaskCompletedEventAttributes()?->getDeploymentVersion();
                    $behavior = $event->getWorkflowTaskCompletedEventAttributes()?->getVersioningBehavior();
                    break;
                }
            }

            self::assertNotNull($version);
            self::assertSame(WorkerFactory::DEPLOYMENT_NAME, $version->getDeploymentName());
            self::assertSame(WorkerFactory::BUILD_ID, $version->getBuildId());
            self::assertSame(VersioningBehavior::Pinned->value, $behavior);
        } finally {
            $starter->stop() and $starter->start();
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
