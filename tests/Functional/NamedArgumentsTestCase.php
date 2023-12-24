<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\ActivityNamedArgumentsWorkflow;
use Temporal\Tests\Workflow\SignalNamedArgumentsWorkflow;
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

    public function testWorkflowStartWithOneParam(): void
    {
        $result = $this->runWorkflow(
            SimpleNamedArgumentsWorkflow::class,
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
            SimpleNamedArgumentsWorkflow::class,
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
            SimpleNamedArgumentsWorkflow::class,
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
            SimpleNamedArgumentsWorkflow::class,
            optionalNullableString: 'test',
            input: 'hello',
        );

        $this->assertSame([
            'input' => 'hello',
            'optionalBool' => false,
            'optionalNullableString' => 'test',
        ], $result);
    }

    public function testActivityNamedParams(): void
    {
        $result = $this->runWorkflow(
            ActivityNamedArgumentsWorkflow::class,
            string: 'hello',
            bool: true,
            secondString: 'test',
        );

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

    private function runSignalWorkflowAndSetValues(...$signalArgs): array
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            SignalNamedArgumentsWorkflow::class
        );

        $run = $this->workflowClient->start($workflow);

        $workflow->setValues(...$signalArgs);

        return $run->getResult('array');
    }

    public function testSignalWorksWithOneParam(): void
    {
        $result = $this->runSignalWorkflowAndSetValues(
            int: 1,
        );

        $this->assertSame([
            'int' => 1,
            'string' => '',
            'bool' => false,
            'nullableString' => null,
            'array' => [],
        ], $result);
    }

    public function testSignalWorksWithParamsInDifferentOrder(): void
    {
        $result = $this->runSignalWorkflowAndSetValues(
            string: 'test',
            int: 1,
            bool: true,
            nullableString: 'test',
            array: ['test'],
        );

        $this->assertSame([
            'int' => 1,
            'string' => 'test',
            'bool' => true,
            'nullableString' => 'test',
            'array' => ['test'],
        ], $result);
    }

    public function testSignalWorksWithMissingParams(): void
    {
        $result = $this->runSignalWorkflowAndSetValues(
            int: 1,
            nullableString: 'test',
        );

        $this->assertSame([
            'int' => 1,
            'string' => '',
            'bool' => false,
            'nullableString' => 'test',
            'array' => [],
        ], $result);
    }

    public function testSignalWorksWithMissingParamAndDifferentOrder(): void
    {
        $result = $this->runSignalWorkflowAndSetValues(
            nullableString: 'test',
            int: 1,
            array: ['test'],
        );

        $this->assertSame([
            'int' => 1,
            'string' => '',
            'bool' => false,
            'nullableString' => 'test',
            'array' => ['test'],
        ], $result);
    }

    /**
     * @dataProvider signalDataProvider
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

    public static function signalDataProvider(): iterable
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
}
