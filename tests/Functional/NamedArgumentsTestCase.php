<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\SimpleNamedArgumentsWorkflow;

final class NamedArgumentsTestCase extends TestCase
{
    private WorkflowClient $workflowClient;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create('localhost:7233')
        );

        parent::setUp();
    }
    private function runWorkflow(...$args)
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            SimpleNamedArgumentsWorkflow::class
        );

        return $this->workflowClient->start(
            $workflow,
            ...$args,
        )->getResult('array');
    }

    public function testWorkflowStartWithOneParam(): void
    {
        $result = $this->runWorkflow(
            input: 'hello',
        );

        $this->assertSame([
            'input' => 'hello',
            'optionalBool' => false,
            'optionalNullableString' => null,
        ], $result);
    }

    public function testWorkflowStartWithParamsInDifferentOrder(): void
    {
        $result = $this->runWorkflow(
            optionalNullableString: 'test',
            input: 'hello',
            optionalBool: true
        );

        $this->assertSame([
            'input' => 'hello',
            'optionalBool' => true,
            'optionalNullableString' => 'test',
        ], $result);
    }

    public function testWorkflowStartWithMissingParams(): void
    {
        $result = $this->runWorkflow(
            input: 'hello',
            optionalNullableString: 'test',
        );

        $this->assertSame([
            'input' => 'hello',
            'optionalBool' => false,
            'optionalNullableString' => 'test',
        ], $result);
    }

    public function testWorkflowStartWithMissingParamAndDifferentOrder(): void
    {
        $result = $this->runWorkflow(
            optionalNullableString: 'test',
            input: 'hello',
        );

        $this->assertSame([
            'input' => 'hello',
            'optionalBool' => false,
            'optionalNullableString' => 'test',
        ], $result);
    }


}
