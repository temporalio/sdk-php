<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Versioning\Deployment;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\Versioning\VersioningBehavior;
use Temporal\Worker\Versioning\WorkerDeploymentOptions;
use Temporal\Worker\Versioning\WorkerDeploymentVersion;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(options: [WorkerFactory::class, 'options'])]
class DeploymentTest extends TestCase
{
    #[Test]
    public function sendEmpty(
        TemporalStarter $starter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $starter->executeTemporalCommand([
            'worker',
            'deployment',
            'set-current-version',
            '--deployment-name', WorkerFactory::DEPLOYMENT_NAME,
            '--build-id', WorkerFactory::BUILD_ID,
            '--yes',
        ], timeout: 5);

        try {
            # Create a Workflow stub with an execution timeout 12 seconds
            $stub = $client
                ->withTimeout(10)
                ->newUntypedWorkflowStub(
                    'Extra_Versioning_Deployment',
                    WorkflowOptions::new()
                        ->withTaskQueue($feature->taskQueue)
                        ->withWorkflowExecutionTimeout(20),
                );

            # Start the Workflow
            $client->start($stub);

            $result = $stub->getResult(timeout: 5);
            self::assertSame('default', $result);

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
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Versioning_Deployment")]
    public function handle()
    {
        return 'default';
    }
}
