<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Versioning\DynamicDeployment;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Testing\Environment;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(options: [WorkerFactory::class, 'options'])]
class DynamicDeploymentTest extends TestCase
{
    #[Test]
    public function defaultBehaviorAuto(
        Environment $environment,
        RRStarter $roadRunnerStarter,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $environment->executeTemporalCommand([
            'worker',
            'deployment',
            'set-current-version',
            '--deployment-name', WorkerFactory::DEPLOYMENT_NAME,
            '--build-id', WorkerFactory::BUILD_ID,
            '--address', $environment->command->address,
            '--yes',
        ], timeout: 5);

        try {
            $stub = $client
                ->withTimeout(10)
                ->newUntypedWorkflowStub(
                    'Extra_Versioning_DynamicDeployment_Default',
                    WorkflowOptions::new()
                        ->withTaskQueue($feature->taskQueue)
                        ->withWorkflowExecutionTimeout(20),
                );
            $client->start($stub);
            $stub->getResult(timeout: 10);

            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->hasWorkflowTaskCompletedEventAttributes()) {
                    self::assertSame(
                        VersioningBehavior::AutoUpgrade,
                        VersioningBehavior::tryFrom(
                            $event->getWorkflowTaskCompletedEventAttributes()->getVersioningBehavior(),
                        ),
                    );
                    return;
                }
            }

            self::fail('The WorkflowTaskCompletedEventAttributes not found in the Workflow history.');
        } finally {
            $roadRunnerStarter->stop();
            $roadRunnerStarter->start();
        }
    }
}

class WorkerFactory
{
    public const DEPLOYMENT_NAME = 'dynamic';
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
class DynamicWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(ValuesInterface $arguments)
    {
        return 'dynamic';
    }
}
