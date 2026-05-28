<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Client\WorkflowClient;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowExecutionStatus;

class WorkflowClientTest extends TestCase
{
    #[Test]
    public function describeWorkflowExecution(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Client_WorkflowClient',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withSearchAttributes([
                    'testFloat' => 1.1,
                    'testInt' => -2,
                    'testBool' => false,
                    'testText' => 'foo',
                    'testKeyword' => 'bar',
                    'testKeywordList' => ['baz'],
                    'testDatetime' => new \DateTimeImmutable('2019-01-01T00:00:00Z'),
                ])
                ->withMemo([
                    'key1' => 'value1',
                    'key2' => 'value2',
                    'key3' => ['foo' => 'bar'],
                    42 => 'value4',
                ]),
        );
        $client->start($stub);

        // Describe running workflow
        $description = $stub->describe();

        self::assertInstanceOf(\DateTimeInterface::class, $description->info->startTime);
        self::assertNull($description->info->closeTime);
        self::assertSame(WorkflowExecutionStatus::Running, $description->info->status);
        self::assertGreaterThanOrEqual(2, $description->info->historyLength);
        self::assertNull($description->info->parentExecution);
        self::assertNotNull($description->info->executionTime);
        self::assertCount(7, $description->info->searchAttributes);
        self::assertCount(4, $description->info->memo);
        self::assertNull($description->info->executionDuration);
        self::assertSame($description->info->firstRunId, $description->info->execution->getRunID());
        self::assertEquals($description->info->execution, $description->info->rootExecution);

        $stub->signal('my_signal', 'test');
        self::assertSame('test', $stub->getResult());

        $description = $stub->describe();
        self::assertNotNull($description->info->executionDuration);
    }
}


#[WorkflowInterface]
class FeatureWorkflow
{
    private string $value = '';

    #[WorkflowMethod('Extra_Client_WorkflowClient')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->value !== '');
        return $this->value;
    }

    #[SignalMethod('my_signal')]
    public function mySignal(string $arg): void
    {
        $this->value = $arg;
    }
}
