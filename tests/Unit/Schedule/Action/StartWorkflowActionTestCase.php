<?php

declare(strict_types=1);

namespace Schedule\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Common\IdReusePolicy as WorkflowIdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\Workflow\WorkflowType;

#[CoversClass(\Temporal\Client\Schedule\Action\StartWorkflowAction::class)]
class StartWorkflowActionTestCase extends TestCase
{
    public function testWithWorkflowTypeString(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $new = $init->withWorkflowType('NewWorkflow');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('TestWorkflow', $init->workflowType->name, 'init value was not changed');
        $this->assertSame('NewWorkflow', $new->workflowType->name);
    }

    public function testWithWorkflowTypeObject(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $wf = new WorkflowType();
        $wf->name = 'NewWorkflow';
        $new = $init->withWorkflowType($wf);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('TestWorkflow', $init->workflowType->name, 'init value was not changed');
        $this->assertSame('NewWorkflow', $new->workflowType->name);
        $this->assertSame($wf, $new->workflowType);
    }

    public function testWithWorkflowId(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $new = $init->withWorkflowId('workflow-id');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('', $init->workflowId, 'init value was not changed');
        $this->assertSame('workflow-id', $new->workflowId);
    }

    public function testWithEmptyWorkflowId(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow')->withWorkflowId('test-id');

        $new = $init->withWorkflowId('');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('test-id', $init->workflowId, 'init value was not changed');
        $this->assertSame('', $new->workflowId);
    }

    public function testWithTaskQueue(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $new = $init->withTaskQueue('task-queue');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('default', $init->taskQueue->name, 'init value was not changed');
        $this->assertSame('task-queue', $new->taskQueue->name);
    }

    #[DataProvider('provideInput')]
    public function testWithInput(mixed $input, array $expect, mixed $initInput = null, array $initExpect = []): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $initInput === null or $init = $init->withInput($initInput);

        $new = $init->withInput($input);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(\count($initExpect), $init->input, 'init value was not changed');
        $this->assertSame($initExpect, $init->input->getValues(), 'init value was not changed');
        $this->assertCount(\count($expect), $new->input);
        $this->assertSame($expect, $new->input->getValues());
    }

    public static function provideInput(): iterable
    {
        yield 'array' => [['foo', 'bar'], ['foo', 'bar']];
        yield 'encoded-vals' => [EncodedValues::fromValues(['foo', 'bar']), ['foo', 'bar']];
        yield 'change-array' => [['foo', 'bar'], ['foo', 'bar'], ['baz', 'qux'], ['baz', 'qux']];
        yield 'change-encoded-vals' => [
            EncodedValues::fromValues(['foo', 'bar']),
            ['foo', 'bar'],
            ['baz', 'qux'],
            ['baz', 'qux'],
        ];
    }

    public static function provideTimeouts(): iterable
    {
        yield 'string' => ['PT15S', '0/0/0/0/0/15'];
        yield 'int' => [15, '0/0/0/0/0/15'];
        yield 'date-interval' => [new \DateInterval('PT15S'), '0/0/0/0/0/15'];
        yield 'change-string' => ['PT15S', '0/0/0/0/0/15', 'PT20S', '0/0/0/0/0/20'];
        yield 'change-int' => [15, '0/0/0/0/0/15', 20, '0/0/0/0/0/20'];
        yield 'change-date-interval' => [
            new \DateInterval('PT15S'),
            '0/0/0/0/0/15',
            new \DateInterval('PT20S'),
            '0/0/0/0/0/20',
        ];
    }

    #[DataProvider('provideTimeouts')]
    public function testWithWorkflowExecutionTimeout(
        mixed $timeout,
        string $expect,
        mixed $initTimeout = null,
        string $initExpect = '0/0/0/0/0/0',
    ): void {
        $init = StartWorkflowAction::new('TestWorkflow');
        $initTimeout === null or $init = $init->withWorkflowExecutionTimeout($initTimeout);

        $new = $init->withWorkflowExecutionTimeout($timeout);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($initExpect, $init->workflowExecutionTimeout->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame($expect, $new->workflowExecutionTimeout->format('%y/%m/%d/%h/%i/%s'));
    }

    #[DataProvider('provideTimeouts')]
    public function testWithWorkflowRunTimeout(
        mixed $timeout,
        string $expect,
        mixed $initTimeout = null,
        string $initExpect = '0/0/0/0/0/0',
    ): void {
        $init = StartWorkflowAction::new('TestWorkflow');
        $initTimeout === null or $init = $init->withWorkflowRunTimeout($initTimeout);

        $new = $init->withWorkflowRunTimeout($timeout);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($initExpect, $init->workflowRunTimeout->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame($expect, $new->workflowRunTimeout->format('%y/%m/%d/%h/%i/%s'));
    }

    #[DataProvider('provideTimeouts')]
    public function testWithWorkflowTaskTimeout(
        mixed $timeout,
        string $expect,
        mixed $initTimeout = null,
        string $initExpect = '0/0/0/0/0/0',
    ): void {
        $init = StartWorkflowAction::new('TestWorkflow');
        $initTimeout === null or $init = $init->withWorkflowTaskTimeout($initTimeout);

        $new = $init->withWorkflowTaskTimeout($timeout);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($initExpect, $init->workflowTaskTimeout->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame($expect, $new->workflowTaskTimeout->format('%y/%m/%d/%h/%i/%s'));
    }

    public function testWithWorkflowIdReusePolicy(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $new = $init->withWorkflowIdReusePolicy(WorkflowIdReusePolicy::AllowDuplicate);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(WorkflowIdReusePolicy::Unspecified, $init->workflowIdReusePolicy);
        $this->assertSame(WorkflowIdReusePolicy::AllowDuplicate, $new->workflowIdReusePolicy);
    }

    public function testWithRetryPolicy(): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $new = $init->withRetryPolicy(RetryOptions::new()->withMaximumAttempts(10));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertNotSame($init->retryPolicy, $new->retryPolicy);
        $this->assertSame(0, $init->retryPolicy->maximumAttempts);
        $this->assertSame(10, $new->retryPolicy->maximumAttempts);
    }

    public static function provideEncodedValues(): iterable
    {
        yield 'array' => [['foo' => 'bar'], ['foo' => 'bar']];
        yield 'generator' => [(static fn() => yield from ['foo' => 'bar'])(), ['foo' => 'bar']];
        yield 'encoded collection' => [EncodedCollection::fromValues(['foo' => 'bar']), ['foo' => 'bar']];
        yield 'change array' => [['foo' => 'bar'], ['foo' => 'bar'], ['baz' => 'qux'], ['baz' => 'qux']];
        yield 'change generator' => [
            (static fn() => yield from ['foo' => 'bar'])(),
            ['foo' => 'bar'],
            (static fn() => yield from ['baz' => 'qux'])(),
            ['baz' => 'qux'],
        ];
        yield 'change encoded collection' => [
            EncodedCollection::fromValues(['foo' => 'bar']),
            ['foo' => 'bar'],
            EncodedCollection::fromValues(['baz' => 'qux']),
            ['baz' => 'qux'],
        ];
        yield 'clear' => [[], [], ['foo' => 'bar'], ['foo' => 'bar']];
    }

    #[DataProvider('provideEncodedValues')]
    public function testWithMemo(mixed $values, array $expect, mixed $initValues = null, array $initExpect = []): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $initValues === null or $init = $init->withMemo($initValues);

        $new = $init->withMemo($values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(\count($initExpect), $init->memo, 'init value was not changed');
        $this->assertSame($initExpect, $init->memo->getValues(), 'init value was not changed');
        $this->assertCount(\count($expect), $new->memo);
        $this->assertSame($expect, $new->memo->getValues());
    }

    #[DataProvider('provideEncodedValues')]
    public function testWithSearchAttributes(mixed $values, array $expect, mixed $initValues = null, array $initExpect = []): void
    {
        $init = StartWorkflowAction::new('TestWorkflow');
        $initValues === null or $init = $init->withSearchAttributes($initValues);

        $new = $init->withSearchAttributes($values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(\count($initExpect), $init->searchAttributes, 'init value was not changed');
        $this->assertSame($initExpect, $init->searchAttributes->getValues(), 'init value was not changed');
        $this->assertCount(\count($expect), $new->searchAttributes);
        $this->assertSame($expect, $new->searchAttributes->getValues());
    }
}
