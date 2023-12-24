<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\Workflow\NamedArguments\ActivityNamedArgumentsWorkflow;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\NamedArguments\ChildSignalNamedArgumentsWorkflow;
use Temporal\Tests\Workflow\NamedArguments\SignalNamedArgumentsWorkflow;
use Temporal\Tests\Workflow\NamedArguments\SimpleNamedArgumentsWorkflow;
use Temporal\Workflow;

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
    private function runWorkflow(string $workflow, ...$args): array
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            $workflow
        );

        return $this->workflowClient->start(
            $workflow,
            ...$args,
        )->getResult('array');
    }

    public function testActivityNamedParams(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            ActivityNamedArgumentsWorkflow::class
        );

        $result = $this->workflowClient->start(
            $workflow,
            string: 'hello',
            bool: true,
            secondString: 'test',
        )->getResult('array');

        $this->assertSame([
            'oneParamRes' => [
                'input' => 'hello',
                'optionalBool' => false,
                'optionalNullableString' => null,
            ],
            'paramsInDifferentOrderRes' => [
                'input' => 'hello',
                'optionalBool' => true,
                'optionalNullableString' => 'test',
            ],
            'missingParamsRes' => [
                'input' => 'hello',
                'optionalBool' => false,
                'optionalNullableString' => 'test',
            ],
            'missingParamAndDifferentOrderRes' => [
                'input' => 'hello',
                'optionalBool' => false,
                'optionalNullableString' => 'test',
            ],
        ], $result);
    }

    public static function argumentsDataProvider(): iterable
    {
        yield 'one param' => [
            ['int' => 1],
            ['int' => 1, 'string' => '', 'bool' => false, 'nullableString' => null, 'array' => []],
        ];

        yield 'params in different order' => [
            ['string' => 'test', 'int' => 1, 'bool' => true, 'nullableString' => 'test', 'array' => ['test']],
            ['int' => 1, 'string' => 'test', 'bool' => true, 'nullableString' => 'test', 'array' => ['test']],
        ];

        yield 'missing params' => [
            ['int' => 1, 'nullableString' => 'test'],
            ['int' => 1, 'string' => '', 'bool' => false, 'nullableString' => 'test', 'array' => []],
        ];

        yield 'missing param and different order' => [
            ['nullableString' => 'test', 'int' => 1, 'array' => ['test']],
            ['int' => 1, 'string' => '', 'bool' => false, 'nullableString' => 'test', 'array' => ['test']],
        ];
    }

    /**
     * @dataProvider argumentsDataProvider
     */
    public function testRunWorkflow(array $args, array $expectedResult): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            SimpleNamedArgumentsWorkflow::class
        );

        $result = $this->workflowClient->start(
            $workflow,
            ...$args,
        )->getResult('array');

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider argumentsDataProvider
     */
    public function testSignalWorkflow(array $signalArgs, array $expectedResult): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            SignalNamedArgumentsWorkflow::class
        );

        $run = $this->workflowClient->start($workflow);

        $workflow->setValues(...$signalArgs);

        $result = $run->getResult('array');

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider argumentsDataProvider
     */
    public function testStartWithSignal(array $signalArgs, array $expectedResult): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            SignalNamedArgumentsWorkflow::class
        );

        $run = $this->workflowClient->startWithSignal(
            $workflow,
            'setValues',
            $signalArgs,
        );

        $result = $run->getResult('array');

        $this->assertSame($expectedResult, $result);
    }

    public function testChildWorkflowSignalNamedArguments()
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            ChildSignalNamedArgumentsWorkflow::class,
        );

        $result = $this->workflowClient->start(
            $workflow,
            int: 1,
            string: 'test',
            bool: true,
            nullableString: 'test',
            array: ['test'],
        )->getResult('array');

        $this->assertSame([
            'oneParamRes' => [
                'int' => 1,
                'string' => '',
                'bool' => false,
                'nullableString' => null,
                'array' => [],
            ],
            'paramsInDifferentOrderRes' => [
                'int' => 1,
                'string' => 'test',
                'bool' => true,
                'nullableString' => 'test',
                'array' => ['test'],
            ],
            'missingParamsRes' => [
                'int' => 1,
                'string' => '',
                'bool' => false,
                'nullableString' => 'test',
                'array' => [],
            ],
            'missingParamAndDifferentOrderRes' => [
                'int' => 1,
                'string' => '',
                'bool' => false,
                'nullableString' => 'test',
                'array' => [],
            ],
        ], $result);
    }
}
