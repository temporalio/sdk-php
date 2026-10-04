<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\DataConverterException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Worker\Core\PayloadMapper;

final class PayloadMapperTestCase extends TestCase
{
    public function testInvalidUtf8MessageFallsBackToScrubbedFailure(): void
    {
        $failure = (new PayloadMapper(DataConverter::createDefault()))->failure(new \RuntimeException("bad \xff byte"));

        self::assertSame('bad ? byte', $failure->getMessage());
        self::assertSame(FailureConverter::SOURCE, $failure->getSource());
        self::assertSame(\RuntimeException::class, $failure->getApplicationFailureInfo()->getType());
    }

    public function testConverterErrorsAreNotHidden(): void
    {
        $resource = \fopen('php://memory', 'rb');
        $error = new ApplicationFailure('message', 'type', false, EncodedValues::fromValues([$resource]));

        try {
            $this->expectException(DataConverterException::class);
            (new PayloadMapper(DataConverter::createDefault()))->failure($error);
        } finally {
            \fclose($resource);
        }
    }

    public function testPlainExceptionFromTheConverterIsNotHidden(): void
    {
        $converter = new class implements DataConverterInterface {
            public function fromPayload(Payload $payload, mixed $type): mixed
            {
                return null;
            }

            public function toPayload(mixed $value): Payload
            {
                throw new \Exception('converter broke');
            }
        };
        $error = new ApplicationFailure('message', 'type', false, EncodedValues::fromValues(['detail']));

        $this->expectExceptionMessage('converter broke');
        (new PayloadMapper($converter))->failure($error);
    }

    public function testFirstPayloadOfMissingOrEmptyValuesIsNull(): void
    {
        $mapper = new PayloadMapper(DataConverter::createDefault());

        self::assertNull($mapper->firstPayload(null));
        self::assertNull($mapper->firstPayload(EncodedValues::empty()));
        self::assertSame('a', $mapper->decode($mapper->firstPayload($mapper->encode(['a', 'b']))));
    }

    public function testFailureRoundTripKeepsTheApplicationFailure(): void
    {
        $mapper = new PayloadMapper(DataConverter::createDefault());

        $exception = $mapper->exception($mapper->failure(new ApplicationFailure('broken', 'Type', true)));

        self::assertInstanceOf(ApplicationFailure::class, $exception);
        self::assertSame('Type', $exception->getType());
        self::assertTrue($exception->isNonRetryable());
    }
}
