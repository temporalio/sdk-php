<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\StandaloneActivitySerializationContext;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Api\Common\V1\Payload;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\ActivityClient;
use Temporal\Client\ActivityClientInterface;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\ProtoConverter;
use Temporal\DataConverter\ProtoJsonConverter;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\ActivityFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;

const SIGNED_ENCODING = 'signed-standalone';

class StandaloneActivitySerializationContextTest extends TestCase
{
    #[Test]
    public function argumentsAndResultUseActivityContext(
        ServiceClientInterface $serviceClient,
        State $runtime,
        Feature $feature,
    ): void {
        $client = $this->client($serviceClient, $runtime);

        $handle = $client->start('Extra_Activity_StandaloneSC.echo', $this->options($feature), new SignedDto('hello'));

        $result = $handle->getResult(SignedDto::class);
        self::assertInstanceOf(SignedDto::class, $result);
        self::assertSame('echo:hello', $result->value);
    }

    #[Test]
    public function resultOfHandleFromIdUsesActivityContext(
        ServiceClientInterface $serviceClient,
        State $runtime,
        Feature $feature,
    ): void {
        $client = $this->client($serviceClient, $runtime);
        $started = $client->start('Extra_Activity_StandaloneSC.echo', $this->options($feature), new SignedDto('hello'));

        $result = $client->getHandle($started->getId())->getResult(SignedDto::class);

        self::assertInstanceOf(SignedDto::class, $result);
        self::assertSame('echo:hello', $result->value);
    }

    #[Test]
    public function failureDetailsUseActivityContext(
        ServiceClientInterface $serviceClient,
        State $runtime,
        Feature $feature,
    ): void {
        $client = $this->client($serviceClient, $runtime);
        $handle = $client->start('Extra_Activity_StandaloneSC.fail', $this->options($feature));

        try {
            $handle->getResult();
            self::fail('Expected ActivityFailedException');
        } catch (ActivityFailedException $e) {
            $cause = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $cause);
            $detail = $cause->getDetails()->getValue(0, SignedDto::class);
            self::assertInstanceOf(SignedDto::class, $detail);
            self::assertSame('boom', $detail->value);
        }
    }

    private function client(ServiceClientInterface $serviceClient, State $runtime): ActivityClientInterface
    {
        return ActivityClient::create(
            serviceClient: $serviceClient,
            options: (new ClientOptions())->withNamespace($runtime->namespace),
            converter: new DataConverter(
                new SignedPayloadConverter(),
                new NullConverter(),
                new BinaryConverter(),
                new ProtoJsonConverter(),
                new ProtoConverter(),
                new JsonConverter(),
            ),
        )->withTimeout(5);
    }

    private function options(Feature $feature): StartActivityOptions
    {
        return StartActivityOptions::new()
            ->withId(Uuid::uuid4()->toString())
            ->withTaskQueue($feature->taskQueue)
            ->withStartToCloseTimeout(30);
    }
}

final class SignedDto
{
    public function __construct(
        public string $value,
    ) {}
}

#[ActivityInterface(prefix: 'Extra_Activity_StandaloneSC.')]
class TestActivity
{
    #[ActivityMethod]
    public function echo(SignedDto $dto): SignedDto
    {
        return new SignedDto('echo:' . $dto->value);
    }

    #[ActivityMethod]
    public function fail(): void
    {
        throw new ApplicationFailure('failed', 'TestError', true, EncodedValues::fromValues([new SignedDto('boom')]));
    }
}

class SignedPayloadConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    private ?SerializationContext $context = null;

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->context;
    }

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

        return (new Payload())
            ->setData(\json_encode($value->value, \JSON_THROW_ON_ERROR))
            ->setMetadata([
                'encoding' => SIGNED_ENCODING,
                'signature' => $this->signature(),
            ]);
    }

    public function fromPayload(Payload $payload, Type $type): SignedDto
    {
        $metadata = $payload->getMetadata();
        $actual = isset($metadata['signature']) ? $metadata['signature'] : '';
        $expected = $this->signature();

        if ($actual !== $expected) {
            throw new \RuntimeException(\sprintf('Signature mismatch: expected %s, got %s', $expected, $actual));
        }

        return new SignedDto((string) \json_decode($payload->getData(), true, flags: \JSON_THROW_ON_ERROR));
    }

    private function signature(): string
    {
        if (!$this->context instanceof ActivitySerializationContext) {
            throw new \LogicException('SignedDto converted without an activity serialization context');
        }

        return \json_encode([
            $this->context->namespace,
            $this->context->workflowId,
            $this->context->workflowType,
            $this->context->activityType,
            $this->context->taskQueue,
            $this->context->isLocal,
        ], \JSON_THROW_ON_ERROR);
    }
}
