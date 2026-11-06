<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Spiral\RoadRunner\Environment;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowExecutionStatus;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowTest extends TestCase
{
    #[Test]
    public function roadRunnerVersionCompatibility(
        #[Stub('Extra_Workflow_Dynamic_Version')]
        WorkflowStubInterface $versionStub,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $version = $versionStub->getResult('string');
        self::assertMatchesRegularExpression('/^v?(3\.|2025\.)/', $version);

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_Dynamic_Compatibility',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowExecutionTimeout('15 seconds'),
        );
        $client->start($stub, 'hello', 42);

        try {
            if (\str_starts_with(\ltrim($version, 'v'), '2025.')) {
                // Older RoadRunner retries unknown types without invoking the PHP handler.
                $deadline = \microtime(true) + 5;
                do {
                    foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                        if ($event->getEventType() !== EventType::EVENT_TYPE_WORKFLOW_TASK_FAILED) {
                            continue;
                        }

                        $message = $event->getWorkflowTaskFailedEventAttributes()->getFailure()->getMessage();
                        self::assertStringContainsString('unable to find workflow type:', $message);
                        self::assertStringContainsString('Extra_Workflow_Dynamic_Compatibility', $message);
                        return;
                    }

                    \usleep(100_000);
                } while (\microtime(true) < $deadline);

                self::fail('Expected RoadRunner v2025 to reject the unregistered workflow type.');
            }

            self::assertSame([
                'type' => 'Extra_Workflow_Dynamic_Compatibility',
                'args' => ['hello', 42],
            ], $stub->getResult('array', 5));
        } finally {
            if ($stub->describe()->info->status === WorkflowExecutionStatus::Running) {
                $stub->terminate('test-end');
            }
        }
    }

    #[Test]
    public function noParameters(
        #[Stub('Extra_Workflow_Dynamic_Empty')]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Empty',
            'args' => [],
        ], $stub->getResult('array'));
    }

    #[Test]
    public function scalarParameters(
        #[Stub('Extra_Workflow_Dynamic_Scalars', args: ['hello', 42])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Scalars',
            'args' => ['hello', 42],
        ], $stub->getResult('array'));
    }

    #[Test]
    public function structuredParameters(
        #[Stub('Extra_Workflow_Dynamic_Structured', args: [
            'pipeline', 0, ['steps' => ['publish', 'notify'], 'enabled' => false],
        ])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Structured',
            'args' => ['pipeline', 0, ['steps' => ['publish', 'notify'], 'enabled' => false]],
        ], $stub->getResult('array'));
    }

    #[Test]
    public function mixedParameters(
        #[Stub('Extra_Workflow_Dynamic_Mixed', args: ['', 0, null, false, 3.5, ['first', 'second'], null])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Mixed',
            'args' => ['', 0, null, false, 3.5, ['first', 'second'], null],
        ], $stub->getResult('array'));
    }
}

#[WorkflowInterface]
class VersionWorkflow
{
    #[WorkflowMethod('Extra_Workflow_Dynamic_Version')]
    public function handle(): string
    {
        return Environment::fromGlobals()->getVersion();
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(ValuesInterface $args): array
    {
        return [
            'type' => Workflow::getInfo()->type->name,
            'args' => $args->getValues(),
        ];
    }
}
