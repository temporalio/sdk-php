<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowOptionsAttribute;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\Attribute\Memo;
use Temporal\Workflow\Attribute\SearchAttributes;
use Temporal\Workflow\Attribute\WorkflowExecutionTimeout;
use Temporal\Workflow\Attribute\WorkflowRunTimeout;
use Temporal\Workflow\Attribute\WorkflowTaskTimeout;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class WorkflowOptionsAttributeTest extends TestCase
{
    #[Test]
    public function workflowOptionsFromAttribute(
        WorkflowClientInterface $workflowClient,
        Feature $feature,
    ): void {
        $workflow = $workflowClient->newWorkflowStub(
            TestWorkflow::class,
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );

        $result = self::toArray($workflow->handle(arg: 'attribute'));

        // WorkflowRunTimeout('1 minute')
        $this->assertSame(60, $result['runTimeout']);
        // WorkflowTaskTimeout('30 seconds')
        $this->assertSame(30, $result['taskTimeout']);
        // WorkflowExecutionTimeout('5 minutes')
        $this->assertSame(300, $result['executionTimeout']);
        // RetryPolicy: all properties
        $this->assertSame(5, $result['retryOptions']['maximumAttempts']);
        $this->assertEqualsWithDelta(3.0, $result['retryOptions']['backoffCoefficient'], 0.001);
        $this->assertSame(2, $result['retryOptions']['initialInterval']);
        $this->assertSame(30, $result['retryOptions']['maximumInterval']);
        $this->assertSame(['SomeException'], $result['retryOptions']['nonRetryableExceptions']);
        // Memo
        $this->assertSame('bar', $result['memo']['foo']);
        $this->assertSame(42, $result['memo']['number']);
        // SearchAttributes
        $this->assertSame('testValue', $result['searchAttributes']['testKeyword']);
        $this->assertSame(123, $result['searchAttributes']['testInt']);
        // Priority: all properties
        $this->assertSame(2, $result['priority']['priorityKey']);
        $this->assertSame('wf-tenant-1', $result['priority']['fairnessKey']);
        $this->assertEqualsWithDelta(2.5, $result['priority']['fairnessWeight'], 0.001);
    }

    #[Test]
    public function workflowOptionsOverriddenInCode(
        WorkflowClientInterface $workflowClient,
        Feature $feature,
    ): void {
        $workflow = $workflowClient->newWorkflowStub(
            TestWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowRunTimeout('2 minutes')
                ->withWorkflowExecutionTimeout('10 minutes')
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(10))
                ->withMemo(['foo' => 'overridden'])
                ->withSearchAttributes(['testKeyword' => 'overriddenValue'])
                ->withPriority(new Priority(priorityKey: 4, fairnessKey: 'wf-override', fairnessWeight: 5.0)),
        );

        $result = self::toArray($workflow->handle(arg: 'code'));

        // Overridden values
        $this->assertSame(120, $result['runTimeout']);
        $this->assertSame(600, $result['executionTimeout']);
        $this->assertSame(4, $result['priority']['priorityKey']);
        $this->assertSame('wf-override', $result['priority']['fairnessKey']);
        $this->assertEqualsWithDelta(5.0, $result['priority']['fairnessWeight'], 0.001);
        $this->assertSame('overridden', $result['memo']['foo']);
        $this->assertSame('overriddenValue', $result['searchAttributes']['testKeyword']);
        // RetryOptions: maximumAttempts overridden, rest from attribute
        $this->assertSame(10, $result['retryOptions']['maximumAttempts']);
        $this->assertEqualsWithDelta(3.0, $result['retryOptions']['backoffCoefficient'], 0.001);
        $this->assertSame(2, $result['retryOptions']['initialInterval']);
        $this->assertSame(30, $result['retryOptions']['maximumInterval']);
        $this->assertSame(['SomeException'], $result['retryOptions']['nonRetryableExceptions']);
        // Not overridden: taskTimeout stays from attribute
        $this->assertSame(30, $result['taskTimeout']);
    }

    private static function toArray(mixed $value): array
    {
        return \json_decode(\json_encode($value), true);
    }
}

#[WorkflowInterface]
#[Memo(['foo' => 'bar', 'number' => 42])]
#[SearchAttributes(['testKeyword' => 'testValue', 'testInt' => 123])]
#[Priority(priorityKey: 2, fairnessKey: 'wf-tenant-1', fairnessWeight: 2.5)]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Workflow_WorkflowOptionsAttribute")]
    #[WorkflowRunTimeout('1 minute')]
    #[WorkflowTaskTimeout('30 seconds')]
    #[WorkflowExecutionTimeout('5 minutes')]
    #[RetryOptions(
        initialInterval: '2 seconds',
        backoffCoefficient: 3.0,
        maximumInterval: '30 seconds',
        maximumAttempts: 5,
        nonRetryableExceptions: ['SomeException'],
    )]
    public function handle(string $arg): array
    {
        $info = Workflow::getInfo();

        return [
            'runTimeout' => (int) $info->runTimeout->totalSeconds,
            'taskTimeout' => (int) $info->taskTimeout->totalSeconds,
            'executionTimeout' => (int) $info->executionTimeout->totalSeconds,
            'retryOptions' => [
                'maximumAttempts' => $info->retryOptions?->maximumAttempts,
                'backoffCoefficient' => $info->retryOptions?->backoffCoefficient,
                'initialInterval' => $info->retryOptions?->initialInterval !== null
                    ? (int) \Carbon\CarbonInterval::instance($info->retryOptions->initialInterval)->totalSeconds
                    : null,
                'maximumInterval' => $info->retryOptions?->maximumInterval !== null
                    ? (int) \Carbon\CarbonInterval::instance($info->retryOptions->maximumInterval)->totalSeconds
                    : null,
                'nonRetryableExceptions' => $info->retryOptions?->nonRetryableExceptions ?? [],
            ],
            'memo' => $info->memo,
            'searchAttributes' => $info->searchAttributes,
            'priority' => [
                'priorityKey' => $info->priority->priorityKey,
                'fairnessKey' => $info->priority->fairnessKey,
                'fairnessWeight' => $info->priority->fairnessWeight,
            ],
        ];
    }
}
