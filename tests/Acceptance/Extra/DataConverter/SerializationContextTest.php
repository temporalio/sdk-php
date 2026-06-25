<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\DataConverter\SerializationContext;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\LocalActivityOptions;
use Temporal\Api\Common\V1\Payload;
use Temporal\Common\RetryOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\HasWorkflowSerializationContext;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

const SIGNED_ENCODING = 'signed';

class SerializationContextTest extends TestCase
{
    private CapturingInterceptor $interceptor;

    protected function setUp(): void
    {
        $this->interceptor = new CapturingInterceptor();
        parent::setUp();
    }

    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([$this->interceptor]);
    }

    #[Test]
    public function everyPayloadIsSignedWithItsWorkflowContext(
        #[Stub(
            'Extra_DataConverter_SerializationContext',
            args: [new SignedDto('hello')],
        )]
        #[Client(
            pipelineProvider: [self::class, 'pipelineProvider'],
            payloadConverters: [SignedPayloadConverter::class],
        )]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(SignedDto::class);

        self::assertInstanceOf(SignedDto::class, $result);
        self::assertSame('echo:hello', $result->value);

        $workflowId = $stub->getExecution()->getID();

        $start = $this->interceptor->start;
        $finish = $this->interceptor->result;
        self::assertNotNull($start);
        self::assertNotNull($finish);

        $startPayload = $start->toPayloads()->getPayloads()[0];
        self::assertSame(SIGNED_ENCODING, $startPayload->getMetadata()['encoding']);
        self::assertSame($workflowId, $startPayload->getMetadata()['signature']);

        $resultPayload = $finish->toPayloads()->getPayloads()[0];
        self::assertSame(SIGNED_ENCODING, $resultPayload->getMetadata()['encoding']);
        self::assertSame($workflowId, $resultPayload->getMetadata()['signature']);
    }

    #[Test]
    public function workflowFailureDetailsDecodeWithWorkflowContext(
        #[Stub('Extra_DataConverter_SerializationContext_Failure')]
        #[Client(payloadConverters: [SignedPayloadConverter::class])]
        WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->getResult();
            self::fail('Expected the workflow to fail');
        } catch (WorkflowFailedException $e) {
            $cause = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $cause);

            $detail = $cause->getDetails()->getValue(0, SignedDto::class);
            self::assertInstanceOf(SignedDto::class, $detail);
            self::assertSame('boom', $detail->value);
        }
    }

    #[Test]
    public function activityFailureDetailsDecodeWithActivityContext(
        #[Stub('Extra_DataConverter_SerializationContext_ActivityFailure')]
        #[Client(payloadConverters: [SignedPayloadConverter::class])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame('activity-detail', $stub->getResult(Type::TYPE_STRING));
    }

    #[Test]
    public function signalQueryUpdateCarryWorkflowContext(
        #[Stub('Extra_DataConverter_SerializationContext_Interactive')]
        #[Client(payloadConverters: [SignedPayloadConverter::class])]
        WorkflowStubInterface $stub,
    ): void {
        $stub->signal('store', new SignedDto('signalled'));

        $queried = $stub->query('current')->getValue(0, SignedDto::class);
        self::assertInstanceOf(SignedDto::class, $queried);
        self::assertSame('signalled', $queried->value);

        $previous = $stub->update('replace', new SignedDto('updated'))->getValue(0, SignedDto::class);
        self::assertInstanceOf(SignedDto::class, $previous);
        self::assertSame('signalled', $previous->value);

        $stub->signal('finish');

        $result = $stub->getResult(SignedDto::class);
        self::assertInstanceOf(SignedDto::class, $result);
        self::assertSame('updated', $result->value);
    }

    #[Test]
    public function continueAsNewArgumentsCarryWorkflowContext(
        #[Stub(
            'Extra_DataConverter_SerializationContext_ContinueAsNew',
            args: [new SignedDto('first')],
        )]
        #[Client(payloadConverters: [SignedPayloadConverter::class])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(SignedDto::class);

        self::assertInstanceOf(SignedDto::class, $result);
        self::assertSame('first-continued', $result->value);
    }

    #[Test]
    public function heartbeatDetailsCarryActivityContext(
        #[Stub('Extra_DataConverter_SerializationContext_Heartbeat')]
        #[Client(payloadConverters: [SignedPayloadConverter::class])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(SignedDto::class);

        self::assertInstanceOf(SignedDto::class, $result);
        self::assertSame('beat', $result->value);
    }
}

final class SignedDto
{
    public function __construct(
        public string $value,
    ) {}
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod(name: 'Extra_DataConverter_SerializationContext')]
    public function handle(SignedDto $input)
    {
        yield Workflow::sideEffect(static fn(): SignedDto => new SignedDto($input->value . '-side'));

        $fromActivity = yield Workflow::executeActivity(
            'Extra_DataConverter_SerializationContext.echo',
            [$input],
            ActivityOptions::new()->withScheduleToCloseTimeout(10),
            SignedDto::class,
        );

        yield Workflow::executeActivity(
            'Extra_DataConverter_SerializationContext.Local.echo',
            [$input],
            LocalActivityOptions::new()->withScheduleToCloseTimeout(10),
            SignedDto::class,
        );

        if (Workflow::getInfo()->parentExecution === null) {
            $child = Workflow::newUntypedChildWorkflowStub(
                'Extra_DataConverter_SerializationContext',
                ChildWorkflowOptions::new()
                    ->withWorkflowId(Workflow::getInfo()->execution->getID() . '-child'),
            );
            yield $child->execute([$input], SignedDto::class);
        }

        return $fromActivity;
    }
}

#[ActivityInterface('Extra_DataConverter_SerializationContext.')]
class FeatureActivity
{
    public function echo(SignedDto $input): SignedDto
    {
        return new SignedDto('echo:' . $input->value);
    }
}

#[ActivityInterface('Extra_DataConverter_SerializationContext.Local.')]
class FeatureLocalActivity
{
    public function echo(SignedDto $input): SignedDto
    {
        return new SignedDto('echo:' . $input->value);
    }
}

#[WorkflowInterface]
class FailingWorkflow
{
    #[WorkflowMethod(name: 'Extra_DataConverter_SerializationContext_Failure')]
    public function handle()
    {
        yield Workflow::timer(1);

        throw new ApplicationFailure(
            'boom',
            'BoomType',
            true,
            EncodedValues::fromValues([new SignedDto('boom')]),
        );
    }
}

#[WorkflowInterface]
class ActivityFailureWorkflow
{
    #[WorkflowMethod(name: 'Extra_DataConverter_SerializationContext_ActivityFailure')]
    public function handle()
    {
        try {
            yield Workflow::executeActivity(
                'Extra_DataConverter_SerializationContext_Failure.fail',
                [],
                ActivityOptions::new()->withScheduleToCloseTimeout(10),
                SignedDto::class,
            );

            return 'no-failure';
        } catch (ActivityFailure $e) {
            $application = $e->getPrevious();
            if (!$application instanceof ApplicationFailure) {
                throw new \RuntimeException('Expected ApplicationFailure cause');
            }

            return $application->getDetails()->getValue(0, SignedDto::class)->value;
        }
    }
}

#[ActivityInterface('Extra_DataConverter_SerializationContext_Failure.')]
class FailingActivity
{
    public function fail(): SignedDto
    {
        throw new ApplicationFailure(
            'activity-boom',
            'BoomType',
            true,
            EncodedValues::fromValues([new SignedDto('activity-detail')]),
        );
    }
}

#[WorkflowInterface]
class InteractiveWorkflow
{
    private ?SignedDto $stored = null;
    private bool $exit = false;

    #[WorkflowMethod(name: 'Extra_DataConverter_SerializationContext_Interactive')]
    public function handle()
    {
        yield Workflow::await(fn(): bool => $this->exit);

        return $this->stored;
    }

    #[Workflow\SignalMethod(name: 'store')]
    public function store(SignedDto $value): void
    {
        $this->stored = $value;
    }

    #[Workflow\QueryMethod(name: 'current')]
    public function current(): ?SignedDto
    {
        return $this->stored;
    }

    #[Workflow\UpdateMethod(name: 'replace')]
    public function replace(SignedDto $value): SignedDto
    {
        $previous = $this->stored ?? new SignedDto('none');
        $this->stored = $value;

        return $previous;
    }

    #[Workflow\SignalMethod(name: 'finish')]
    public function finish(): void
    {
        $this->exit = true;
    }
}

#[WorkflowInterface]
class ContinueAsNewWorkflow
{
    #[WorkflowMethod(name: 'Extra_DataConverter_SerializationContext_ContinueAsNew')]
    public function handle(SignedDto $input)
    {
        if (!empty(Workflow::getInfo()->continuedExecutionRunId)) {
            return $input;
        }

        return yield Workflow::continueAsNew(
            'Extra_DataConverter_SerializationContext_ContinueAsNew',
            args: [new SignedDto($input->value . '-continued')],
        );
    }
}

#[WorkflowInterface]
class HeartbeatWorkflow
{
    #[WorkflowMethod(name: 'Extra_DataConverter_SerializationContext_Heartbeat')]
    public function handle()
    {
        return yield Workflow::executeActivity(
            'Extra_DataConverter_SerializationContext_Heartbeat.run',
            [],
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(20)
                ->withHeartbeatTimeout(10)
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withInitialInterval(1)
                        ->withBackoffCoefficient(1)
                        ->withMaximumAttempts(2),
                ),
            SignedDto::class,
        );
    }
}

#[ActivityInterface('Extra_DataConverter_SerializationContext_Heartbeat.')]
class HeartbeatActivity
{
    public function run(): SignedDto
    {
        if (Activity::hasHeartbeatDetails()) {
            return Activity::getHeartbeatDetails(SignedDto::class);
        }

        Activity::heartbeat(new SignedDto('beat'));

        throw new \RuntimeException('retry to read heartbeat details');
    }
}

class CapturingInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public ?EncodedValues $start = null;
    public ?EncodedValues $result = null;

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        $this->start = $input->arguments;
        return $next($input);
    }

    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $this->result = $next($input);
    }
}

class SignedPayloadConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    private ?SerializationContext $context = null;

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->context = $context;
        return $clone;
    }

    public function getEncodingType(): string
    {
        return SIGNED_ENCODING;
    }

    public function toPayload($value): ?Payload
    {
        if (!$value instanceof SignedDto) {
            return null;
        }

        if ($this->context === null) {
            throw new \LogicException('SignedDto serialized without a serialization context');
        }

        return (new Payload())
            ->setData(\json_encode($value->value, \JSON_THROW_ON_ERROR))
            ->setMetadata([
                'encoding' => SIGNED_ENCODING,
                'signature' => $this->signature($this->context),
            ]);
    }

    public function fromPayload(Payload $payload, Type $type): SignedDto
    {
        if ($this->context === null) {
            throw new \LogicException('Signed payload decoded without a serialization context');
        }

        $metadata = $payload->getMetadata();
        $actual = isset($metadata['signature']) ? $metadata['signature'] : '';
        $expected = $this->signature($this->context);

        if ($actual !== $expected) {
            throw new \RuntimeException(
                \sprintf('Signature mismatch: expected "%s", got "%s"', $expected, $actual),
            );
        }

        return new SignedDto((string) \json_decode($payload->getData(), true, flags: \JSON_THROW_ON_ERROR));
    }

    private function signature(SerializationContext $context): string
    {
        if ($context instanceof ActivitySerializationContext) {
            return (string) $context->workflowId . ':' . (string) $context->activityType;
        }

        if ($context instanceof HasWorkflowSerializationContext) {
            return (string) $context->getWorkflowId();
        }

        return '';
    }
}
