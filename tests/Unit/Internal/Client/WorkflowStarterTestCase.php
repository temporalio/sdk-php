<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Client\WorkflowStarter;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Internal\Nexus\OnConflictOptions;
use Temporal\Internal\Support\DateInterval;
use Temporal\Nexus\Link as NexusLink;
use Temporal\Workflow\CompletionCallback;

/**
 * @internal
 *
 */
#[CoversClass(\Temporal\Internal\Client\WorkflowStub::class)]
final class WorkflowStarterTestCase extends TestCase
{
    private const NAMESPACE = 'test-namespace';
    private const IDENTITY = 'test-identity';
    private const WORKFLOW_RUN_ID = 'test-run-id';

    /**
     * @link https://github.com/temporalio/sdk-php/issues/387
     */
    public function testDelayIsNullIfNotSpecified(): void
    {
        $options = new WorkflowOptions();

        $request = $this->startRequest('test-workflow', $options);

        self::assertNull($request->getWorkflowStartDelay());
    }

    public function testDelayIsNullIfEmpty(): void
    {
        $options = (new WorkflowOptions())->withWorkflowStartDelay(0);

        $request = $this->startRequest('test-workflow', $options);

        self::assertNull($request->getWorkflowStartDelay());
    }

    public function testDelayIfSpecifiedSeconds(): void
    {
        $options = (new WorkflowOptions())->withWorkflowStartDelay(10);

        $request = $this->startRequest('test-workflow', $options);

        self::assertNotNull($request->getWorkflowStartDelay());
        self::assertSame(10, $request->getWorkflowStartDelay()->getSeconds());
        self::assertSame(0, $request->getWorkflowStartDelay()->getNanos());
    }

    public function testDelayIfSpecifiedNanos(): void
    {
        $options = (new WorkflowOptions())
            ->withWorkflowStartDelay(DateInterval::parse(42, DateInterval::FORMAT_MICROSECONDS));

        $request = $this->startRequest('test-workflow', $options);

        self::assertNotNull($request->getWorkflowStartDelay());
        self::assertSame(0, $request->getWorkflowStartDelay()->getSeconds());
        self::assertSame(42000, $request->getWorkflowStartDelay()->getNanos());
    }

    public function testOnConflictOptionsAbsentByDefault(): void
    {
        $request = $this->startRequest('test-workflow', new WorkflowOptions());

        self::assertNull($request->getOnConflictOptions());
    }

    public function testOnConflictOptionsSerializedToProtoWithAllFlags(): void
    {
        $options = (new WorkflowOptions())
            ->withOnConflictOptionsInternal(OnConflictOptions::forNexusCompletionCallback());

        $request = $this->startRequest('test-workflow', $options);

        $proto = $request->getOnConflictOptions();
        self::assertNotNull($proto);
        self::assertTrue($proto->getAttachRequestId());
        self::assertTrue($proto->getAttachCompletionCallbacks());
        self::assertTrue($proto->getAttachLinks());
    }

    public function testOnConflictOptionsSerializedToProtoWithMixedFlags(): void
    {
        $options = (new WorkflowOptions())
            ->withOnConflictOptionsInternal(new OnConflictOptions(
                attachRequestId: false,
                attachCompletionCallbacks: true,
                attachLinks: false,
            ));

        $request = $this->startRequest('test-workflow', $options);

        $proto = $request->getOnConflictOptions();
        self::assertNotNull($proto);
        self::assertFalse($proto->getAttachRequestId());
        self::assertTrue($proto->getAttachCompletionCallbacks());
        self::assertFalse($proto->getAttachLinks());
    }

    public function testWorkflowIdConflictPolicyUseExistingFlowsToProto(): void
    {
        $options = (new WorkflowOptions())
            ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::UseExisting);

        $request = $this->startRequest('test-workflow', $options);

        self::assertSame(WorkflowIdConflictPolicy::UseExisting->value, $request->getWorkflowIdConflictPolicy());
    }

    public function testStartRequestCarriesTopLevelLinks(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history'
            . '?referenceType=EventReference&eventID=1&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $options = (new WorkflowOptions())
            ->withLinks([new NexusLink($uri, NexusLinkConverter::TYPE_WORKFLOW_EVENT)]);

        $request = $this->startRequest('test-workflow', $options);

        $links = \iterator_to_array($request->getLinks());
        self::assertCount(1, $links);
        self::assertNotNull($links[0]->getWorkflowEvent());
    }

    public function testCompletionCallbackProtoIncludesLinks(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history'
            . '?referenceType=EventReference&eventID=1&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $callback = CompletionCallback::withNexusLinks(
            'http://cb',
            [],
            [new NexusLink($uri, NexusLinkConverter::TYPE_WORKFLOW_EVENT)],
        );
        $options = (new WorkflowOptions())->withCompletionCallbacks($callback);

        $request = $this->startRequest('test-workflow', $options);

        $callbacks = \iterator_to_array($request->getCompletionCallbacks());
        self::assertCount(1, $callbacks);
        $links = \iterator_to_array($callbacks[0]->getLinks());
        self::assertCount(1, $links);
        self::assertNotNull($links[0]->getWorkflowEvent());
    }

    private function startRequest(
        string $workflowType,
        WorkflowOptions $options,
        array $args = [],
    ): StartWorkflowExecutionRequest {
        $clientOptions = (new \Temporal\Client\ClientOptions())
            ->withNamespace(self::NAMESPACE)
            ->withIdentity(self::IDENTITY);

        $result = null;
        $clientMock = $this->createMock(ServiceClientInterface::class);
        $clientMock
            ->expects($this->once())
            ->method('StartWorkflowExecution')
            ->with(
                $this->callback(static function (StartWorkflowExecutionRequest $request) use (&$result) {
                    $result = $request;
                    return true;
                })
            )->willReturn(
                (new StartWorkflowExecutionResponse())
                    ->setRunId(self::WORKFLOW_RUN_ID)
            );

        $starter = new WorkflowStarter(
            serviceClient: $clientMock,
            converter: DataConverter::createDefault(),
            clientOptions: $clientOptions,
            interceptors: Pipeline::prepare([]),
        );

        $execution = $starter->start($workflowType, $options, $args);

        if (self::WORKFLOW_RUN_ID !== $execution->getRunID()) {
            $this->fail('Unexpected workflow run ID.');
        }

        return $result;
    }
}
