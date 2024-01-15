<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Client\WorkflowStarter;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Support\DateInterval;

/**
 * @internal
 *
 * @covers \Temporal\Internal\Client\WorkflowStub
 */
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
