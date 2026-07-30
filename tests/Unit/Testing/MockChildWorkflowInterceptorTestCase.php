<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Interceptor\Header;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\GetChildWorkflowExecution;
use Temporal\Testing\MockChildWorkflowInterceptor;
use Temporal\Testing\WorkflowMocker;
use Temporal\Tests\TestCase;
use Temporal\Worker\InvocationResult;
use Temporal\Worker\ChildWorkflowInvocationCache\InMemoryChildWorkflowInvocationCache;
use Temporal\Workflow\WorkflowExecution;

use function React\Promise\resolve;

final class MockChildWorkflowInterceptorTestCase extends TestCase
{
    private InMemoryChildWorkflowInvocationCache $cache;
    private WorkflowMocker $mocker;
    private MockChildWorkflowInterceptor $interceptor;

    protected function setUp(): void
    {
        $this->cache = new InMemoryChildWorkflowInvocationCache();
        $this->mocker = new WorkflowMocker($this->cache);
        $this->interceptor = new MockChildWorkflowInterceptor($this->cache);

        parent::setUp();
    }

    public function testMockedChildWorkflowResolvesWithoutCallingNext(): void
    {
        $this->mocker->expectCompletion('SimpleWorkflow', 'mocked-result');

        $request = $this->executeChildRequest('SimpleWorkflow');
        $nextCalled = false;

        $result = $this->interceptor->handleOutboundRequest(
            $request,
            static function () use (&$nextCalled) {
                $nextCalled = true;
                return resolve('real-result');
            },
        );

        self::assertFalse($nextCalled);

        $decoded = null;
        EncodedValues::decodePromise($result, 'string')->then(static function ($value) use (&$decoded): void {
            $decoded = $value;
        });

        self::assertSame('mocked-result', $decoded);
    }

    public function testAppliedMockIsRecordedForAssertInvoked(): void
    {
        $this->mocker->expectCompletion('SimpleWorkflow', 'mocked-result');
        self::assertFalse($this->mocker->wasInvoked('SimpleWorkflow'));

        $this->interceptor->handleOutboundRequest(
            $this->executeChildRequest('SimpleWorkflow'),
            static fn() => resolve('real-result'),
        );

        $this->mocker->assertInvoked('SimpleWorkflow');
        $this->mocker->assertNotInvoked('OtherWorkflow');
    }

    public function testGetChildWorkflowExecutionResolvesFakeExecutionForMockedChild(): void
    {
        $this->mocker->expectCompletion('SimpleWorkflow', 'mocked-result');

        $executeRequest = $this->executeChildRequest('SimpleWorkflow');
        $this->interceptor->handleOutboundRequest(
            $executeRequest,
            static fn() => self::fail('next should not be called for mocked child'),
        );

        $getRequest = new GetChildWorkflowExecution($executeRequest);
        $nextCalled = false;

        $started = $this->interceptor->handleOutboundRequest(
            $getRequest,
            static function () use (&$nextCalled) {
                $nextCalled = true;
                return resolve(EncodedValues::fromValues([new WorkflowExecution()]));
            },
        );

        self::assertFalse($nextCalled);

        $execution = null;
        $started->then(static function (EncodedValues $values) use (&$execution): void {
            $execution = $values->getValue(0, WorkflowExecution::class);
        });

        self::assertInstanceOf(WorkflowExecution::class, $execution);
    }

    public function testUnmockedChildWorkflowPassesThrough(): void
    {
        $this->mocker->expectCompletion('MockedWorkflow', 'mocked');

        $executeNextCalled = false;
        $this->interceptor->handleOutboundRequest(
            $this->executeChildRequest('UnmockedWorkflow'),
            static function () use (&$executeNextCalled) {
                $executeNextCalled = true;
                return resolve(EncodedValues::empty());
            },
        );
        self::assertTrue($executeNextCalled);

        $getRequest = new GetChildWorkflowExecution($this->executeChildRequest('UnmockedWorkflow'));
        $getNextCalled = false;
        $this->interceptor->handleOutboundRequest(
            $getRequest,
            static function () use (&$getNextCalled) {
                $getNextCalled = true;
                return resolve(EncodedValues::fromValues([new WorkflowExecution()]));
            },
        );
        self::assertTrue($getNextCalled);
    }

    public function testMockedFailureRejectsResult(): void
    {
        $this->mocker->expectFailure('SimpleWorkflow', new \RuntimeException('child boom'));

        $result = $this->interceptor->handleOutboundRequest(
            $this->executeChildRequest('SimpleWorkflow'),
            static fn() => self::fail('next should not be called for mocked child'),
        );

        $caught = null;
        $result->then(
            static fn() => self::fail('mocked failure must reject'),
            static function (\Throwable $error) use (&$caught): void {
                $caught = $error;
            },
        );

        self::assertInstanceOf(ChildWorkflowFailure::class, $caught);

        $execution = $caught->getExecution();
        self::assertStringStartsWith('mocked-child-', $execution->getID());
        self::assertStringStartsWith('mocked-run-', (string) $execution->getRunID());

        $cause = $caught->getPrevious();
        self::assertInstanceOf(ApplicationFailure::class, $cause);
        self::assertSame('RuntimeException', $cause->getType());
        self::assertSame('child boom', $cause->getOriginalMessage());
    }

    public function testInvocationResultSurvivesSerializationRoundTrip(): void
    {
        $dataConverter = DataConverter::createDefault();
        $result = InvocationResult::fromValue('payload-value', $dataConverter);

        $restored = \unserialize(\serialize($result));

        self::assertInstanceOf(InvocationResult::class, $restored);
        self::assertSame('payload-value', $restored->toValue('string', $dataConverter));
    }

    public function testArgMatchedChildResolvesMatchingCase(): void
    {
        $this->mocker->expectCompletionWhen('SimpleWorkflow', ['A'], 'result-A');
        $this->mocker->expectCompletionWhen('SimpleWorkflow', ['B'], 'result-B');

        self::assertSame('result-A', $this->resolveChild('SimpleWorkflow', ['A']));
        self::assertSame('result-B', $this->resolveChild('SimpleWorkflow', ['B']));
    }

    public function testArgMatchedChildFallsThroughOnNoMatch(): void
    {
        $this->mocker->expectCompletionWhen('SimpleWorkflow', ['A'], 'result-A');

        $nextCalled = false;
        $this->interceptor->handleOutboundRequest(
            $this->executeChildRequest('SimpleWorkflow', ['Z']),
            static function () use (&$nextCalled) {
                $nextCalled = true;
                return resolve(EncodedValues::empty());
            },
        );

        self::assertTrue($nextCalled);
    }

    private function resolveChild(string $workflowType, array $input): mixed
    {
        $result = $this->interceptor->handleOutboundRequest(
            $this->executeChildRequest($workflowType, $input),
            static fn() => self::fail('next should not be called for matched child'),
        );

        $decoded = null;
        EncodedValues::decodePromise($result, 'string')->then(static function ($value) use (&$decoded): void {
            $decoded = $value;
        });

        return $decoded;
    }

    private function executeChildRequest(string $workflowType, array $input = ['input']): ExecuteChildWorkflow
    {
        return new ExecuteChildWorkflow(
            $workflowType,
            EncodedValues::fromValues($input),
            [],
            Header::empty(),
        );
    }
}
