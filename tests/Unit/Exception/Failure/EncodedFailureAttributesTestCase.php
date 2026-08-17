<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception\Failure;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Worker\FeatureFlags;

/**
 * @link https://github.com/temporalio/sdk-php/issues/454
 */
final class EncodedFailureAttributesTestCase extends TestCase
{
    private bool $flag;

    protected function setUp(): void
    {
        $this->flag = FeatureFlags::$encodeFailureAttributes;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        FeatureFlags::$encodeFailureAttributes = $this->flag;
        parent::tearDown();
    }

    public function testMessageAndStackTraceAreMovedIntoEncodedAttributes(): void
    {
        $converter = DataConverter::createDefault();
        $plain = self::mapWithFlag(false, $converter);

        $failure = self::mapWithFlag(true, $converter);

        self::assertSame('Encoded failure', $failure->getMessage());
        self::assertSame('', $failure->getStackTrace());

        $payload = $failure->getEncodedAttributes();
        self::assertInstanceOf(Payload::class, $payload);

        $attributes = $converter->fromPayload($payload, Type::TYPE_ARRAY);
        self::assertSame($plain->getMessage(), $attributes['message']);
        self::assertArrayHasKey('stack_trace', $attributes);
        self::assertNotSame('', $attributes['stack_trace']);
    }

    public function testCauseIsEncodedRecursively(): void
    {
        FeatureFlags::$encodeFailureAttributes = true;
        $converter = DataConverter::createDefault();

        $failure = FailureConverter::mapExceptionToFailure(
            new ApplicationFailure(
                'main error',
                'MainError',
                true,
                previous: new ApplicationFailure('cause error', 'CauseError', true),
            ),
            $converter,
        );

        $cause = $failure->getCause();
        self::assertNotNull($cause);
        self::assertSame('Encoded failure', $cause->getMessage());
        self::assertSame('', $cause->getStackTrace());

        $attributes = $converter->fromPayload($cause->getEncodedAttributes(), Type::TYPE_ARRAY);
        self::assertStringContainsString('cause error', $attributes['message']);
    }

    public function testRoundTripRestoresMessageAndStackTrace(): void
    {
        $converter = DataConverter::createDefault();
        // The same exception instance, so both mappings produce the very same stack trace.
        $exception = new ApplicationFailure('main error', 'MainError', true);

        FeatureFlags::$encodeFailureAttributes = false;
        $plainFailure = FailureConverter::mapExceptionToFailure($exception, $converter);

        FeatureFlags::$encodeFailureAttributes = true;
        $encodedFailure = FailureConverter::mapExceptionToFailure($exception, $converter);

        $plain = FailureConverter::mapFailureToException($plainFailure, $converter);
        $restored = FailureConverter::mapFailureToException($encodedFailure, $converter);

        self::assertSame($plain->getOriginalMessage(), $restored->getOriginalMessage());
        self::assertSame($plain->getOriginalStackTrace(), $restored->getOriginalStackTrace());
    }

    public function testDisabledByDefaultKeepsPlainAttributes(): void
    {
        $failure = self::mapWithFlag(false, DataConverter::createDefault());

        self::assertNotSame('Encoded failure', $failure->getMessage());
        self::assertStringContainsString('main error', $failure->getMessage());
        self::assertNotSame('', $failure->getStackTrace());
        self::assertNull($failure->getEncodedAttributes());
    }

    private static function mapWithFlag(bool $encode, DataConverter $converter): \Temporal\Api\Failure\V1\Failure
    {
        FeatureFlags::$encodeFailureAttributes = $encode;

        return FailureConverter::mapExceptionToFailure(
            new ApplicationFailure('main error', 'MainError', true),
            $converter,
        );
    }

    /**
     * The encoded attributes are a regular payload, so they must be converted with the
     * serialization context of the failure they belong to.
     *
     * @link https://github.com/temporalio/features/issues/434
     */
    public function testEncodedAttributesAreConvertedWithSerializationContext(): void
    {
        FeatureFlags::$encodeFailureAttributes = true;
        $converter = new DataConverter(new FailureAttributesSigningConverter());
        $context = new WorkflowSerializationContext('test-ns', 'wf-1');

        $failure = FailureConverter::mapExceptionToFailure(
            new ApplicationFailure('main error', 'MainError', true),
            $converter,
            $context,
        );

        $payload = $failure->getEncodedAttributes();
        self::assertInstanceOf(Payload::class, $payload);
        self::assertSame(
            'wf:test-ns:wf-1',
            $payload->getMetadata()[FailureAttributesSigningConverter::KEY] ?? '',
        );
    }
}

final class FailureAttributesSigningConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    public const KEY = 'ctx-signature';

    private ?SerializationContext $context = null;

    public function getEncodingType(): string
    {
        return 'json/plain';
    }

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

    public function toPayload($value): ?Payload
    {
        return (new Payload())
            ->setData((string) \json_encode($value))
            ->setMetadata(['encoding' => $this->getEncodingType(), self::KEY => $this->signature()]);
    }

    public function fromPayload(Payload $payload, Type $type): mixed
    {
        return \json_decode($payload->getData(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function signature(): string
    {
        return $this->context instanceof WorkflowSerializationContext
            ? 'wf:' . $this->context->namespace . ':' . $this->context->workflowId
            : 'none';
    }
}
