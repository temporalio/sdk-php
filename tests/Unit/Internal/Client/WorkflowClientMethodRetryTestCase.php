<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Common\RetryOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RetriedCronWorkflow
{
    #[WorkflowMethod(name: 'RetriedCronWorkflow')]
    #[MethodRetry(maximumAttempts: 5, maximumInterval: '30 seconds')]
    #[CronSchedule(interval: '0 * * * *')]
    public function handle(): void;
}

#[CoversClass(WorkflowClient::class)]
final class WorkflowClientMethodRetryTestCase extends TestCase
{
    public function testRetryPolicyComesFromMethodRetry(): void
    {
        $request = $this->startRequest(null);

        self::assertSame(5, $request->getRetryPolicy()?->getMaximumAttempts());
        self::assertSame(30, $request->getRetryPolicy()?->getMaximumInterval()?->getSeconds());
    }

    public function testUserRetryOptionsTakePrecedenceOverMethodRetry(): void
    {
        $request = $this->startRequest(
            WorkflowOptions::new()->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        self::assertSame(1, $request->getRetryPolicy()?->getMaximumAttempts());
        self::assertSame(30, $request->getRetryPolicy()?->getMaximumInterval()?->getSeconds());
    }

    public function testCronScheduleIsIgnored(): void
    {
        $request = $this->startRequest(null);

        self::assertSame('', $request->getCronSchedule());
    }

    private function startRequest(?WorkflowOptions $options): StartWorkflowExecutionRequest
    {
        $captured = null;
        $service = $this->createMock(ServiceClientInterface::class);
        $service->method('withContext')->willReturnSelf();
        $service->method('StartWorkflowExecution')->willReturnCallback(
            static function (StartWorkflowExecutionRequest $request) use (&$captured): StartWorkflowExecutionResponse {
                $captured = $request;
                return (new StartWorkflowExecutionResponse())->setRunId('run-1');
            },
        );

        $client = new WorkflowClient($service);
        $client->start($client->newWorkflowStub(RetriedCronWorkflow::class, $options));

        return $captured;
    }
}
