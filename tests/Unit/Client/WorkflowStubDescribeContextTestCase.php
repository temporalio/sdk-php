<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Api\Common\V1\ActivityType as ProtoActivityType;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\WorkflowExecution as ProtoWorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType as ProtoWorkflowType;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflow\V1\PendingActivityInfo as ProtoPendingActivityInfo;
use Temporal\Api\Workflow\V1\WorkflowExecutionConfig;
use Temporal\Api\Workflow\V1\WorkflowExecutionInfo;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionResponse;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Client\WorkflowStub;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\PendingActivityInfo;

#[CoversClass(WorkflowStub::class)]
#[CoversClass(PendingActivityInfo::class)]
final class WorkflowStubDescribeContextTestCase extends AbstractUnit
{
    private ?DescribeWorkflowExecutionResponse $describeResponse = null;

    public function testDescribeDecodesPendingActivityDetailsWithActivityContext(): void
    {
        $failure = (new Failure())
            ->setMessage('boom')
            ->setApplicationFailureInfo(
                (new ApplicationFailureInfo())
                    ->setType('Boom')
                    ->setDetails(EncodedValues::fromValues([new Stamped()], self::converter())->toPayloads()),
            );
        $this->describeResponse = (new DescribeWorkflowExecutionResponse())
            ->setExecutionConfig((new WorkflowExecutionConfig())->setTaskQueue((new TaskQueue())->setName('workflow-queue')))
            ->setWorkflowExecutionInfo(
                (new WorkflowExecutionInfo())
                    ->setExecution((new ProtoWorkflowExecution())->setWorkflowId('workflow-id')->setRunId('run-id'))
                    ->setType((new ProtoWorkflowType())->setName('WorkflowType'))
                    ->setTaskQueue('workflow-queue')
                    ->setMemo(new Memo(['fields' => ['key' => $this->stampedPayload()]])),
            )
            ->setPendingActivities([
                (new ProtoPendingActivityInfo())
                    ->setActivityId('1')
                    ->setActivityType((new ProtoActivityType())->setName('DoWork'))
                    ->setHeartbeatDetails(EncodedValues::fromValues([new Stamped()], self::converter())->toPayloads())
                    ->setLastFailure($failure),
            ]);

        $description = $this->stub()->describe();

        $activity = $description->pendingActivities[0];
        $expected = 'decoded with activity|client-ns|DoWork|workflow-queue|workflow-id|WorkflowType';
        self::assertSame($expected, $activity->heartbeatDetails->getValue(0, Stamped::class));
        self::assertInstanceOf(ApplicationFailure::class, $activity->lastFailure);
        self::assertSame($expected, $activity->lastFailure->getDetails()->getValue(0, Stamped::class));
        self::assertSame(
            'decoded with none',
            $description->info->memo->getValue('key', Stamped::class),
        );
    }

    private function stub(): WorkflowStubInterface
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getMetadata')->willReturn([]);
        $context->method('withMetadata')->willReturn($context);

        $client = $this->createMock(ServiceClientInterface::class);
        $client->method('getContext')->willReturn($context);
        $client->method('withContext')->willReturn($client);
        $client->method('DescribeWorkflowExecution')->willReturnCallback(
            fn(): DescribeWorkflowExecutionResponse => $this->describeResponse,
        );

        return WorkflowClient::create(
            $client,
            (new ClientOptions())->withNamespace('client-ns'),
            self::converter(),
            new SimplePipelineProvider(),
        )->newUntypedRunningWorkflowStub('workflow-id', 'run-id', 'WorkflowType');
    }

    private function stampedPayload(): Payload
    {
        return EncodedValues::fromValues([new Stamped()], self::converter())->toPayloads()->getPayloads()[0];
    }

    private static function converter(): DataConverter
    {
        return new DataConverter(new StampConverter(), new JsonConverter());
    }
}

final class Stamped {}

final class StampConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    private string $context = 'none';

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->context = match (true) {
            $context instanceof ActivitySerializationContext => \implode('|', [
                'activity',
                $context->namespace,
                $context->activityType,
                $context->taskQueue,
                (string) $context->workflowId,
                (string) $context->workflowType,
            ]),
            $context instanceof WorkflowSerializationContext => 'workflow|' . $context->namespace . '|' . $context->workflowId,
            default => 'none',
        };

        return $clone;
    }

    public function getEncodingType(): string
    {
        return 'test/stamp';
    }

    public function toPayload($value): ?Payload
    {
        if (!$value instanceof Stamped) {
            return null;
        }

        return (new Payload())->setData('')->setMetadata(['encoding' => 'test/stamp', 'context' => $this->context]);
    }

    public function fromPayload(Payload $payload, Type $type): string
    {
        return 'decoded with ' . $this->context;
    }
}
