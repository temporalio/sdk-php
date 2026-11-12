<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowInit;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInit;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowInitTest extends TestCase
{
    #[Test]
    public function constructorAndHandlerReceiveRawArguments(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        foreach ([['hello', 42, null, false], []] as $arguments) {
            $stub = $client->newUntypedWorkflowStub(
                'Extra_Workflow_Dynamic_Init',
                WorkflowOptions::new()
                    ->withTaskQueue($feature->taskQueue)
                    ->withWorkflowExecutionTimeout('15 seconds'),
            );
            $client->start($stub, ...$arguments);

            self::assertSame([
                'constructor' => $arguments,
                'handler' => $arguments,
            ], $stub->getResult('array', 5));
        }
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowInit]
    public function __construct(private ValuesInterface $arguments) {}

    #[WorkflowMethod(dynamic: true)]
    public function handle(ValuesInterface $arguments): array
    {
        return [
            'constructor' => $this->arguments->getValues(),
            'handler' => $arguments->getValues(),
        ];
    }
}
