<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception\Failure;

use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Failure\V1\NexusHandlerFailureInfo;
use Temporal\DataConverter\DataConverter;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Exception\Failure\NexusHandlerFailure;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\RetryBehavior;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(NexusHandlerFailure::class)]
#[UsesClass(ErrorType::class)]
#[UsesClass(RetryBehavior::class)]
final class NexusHandlerFailureTestCase extends AbstractUnit
{
    /** @return iterable<string, array{0: string, 1: ErrorType}> */
    public static function knownErrorTypes(): iterable
    {
        foreach (ErrorType::cases() as $case) {
            yield "{$case->value}" => [$case->value, $case];
        }
    }

    #[DataProvider('knownErrorTypes')]
    public function testGetErrorTypeReturnsTypedEnumForKnownWireValue(
        string $rawType,
        ErrorType $expected,
    ): void {
        $failure = new NexusHandlerFailure('m', $rawType, 0);

        self::assertSame($expected, $failure->getErrorType());
        self::assertSame($rawType, $failure->getType(), 'Raw string preserved alongside typed view');
    }

    public function testGetErrorTypeFallsBackToUnknownForUnrecognizedWireValue(): void
    {
        $failure = new NexusHandlerFailure('m', 'FUTURE_TYPE_X', 0);

        self::assertSame(ErrorType::Unknown, $failure->getErrorType());
        self::assertSame('FUTURE_TYPE_X', $failure->getType(), 'Raw string preserved');
    }

    /** @return iterable<string, array{0: int, 1: RetryBehavior}> */
    public static function retryBehaviors(): iterable
    {
        yield 'Unspecified' => [
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_UNSPECIFIED,
            RetryBehavior::Unspecified,
        ];
        yield 'Retryable' => [
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_RETRYABLE,
            RetryBehavior::Retryable,
        ];
        yield 'NonRetryable' => [
            NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE,
            RetryBehavior::NonRetryable,
        ];
    }

    #[DataProvider('retryBehaviors')]
    public function testGetRetryBehaviorEnumMapsAllProtoValues(int $proto, RetryBehavior $expected): void
    {
        $failure = new NexusHandlerFailure('m', 'BAD_REQUEST', $proto);

        self::assertSame($expected, $failure->getRetryBehaviorEnum());
        self::assertSame($proto, $failure->getRetryBehavior(), 'Raw int preserved alongside typed view');
    }

    public function testTypedAccessorsOnConsumePathFromProto(): void
    {
        $info = new NexusHandlerFailureInfo();
        $info->setType('NOT_FOUND');
        $info->setRetryBehavior(NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE);

        $failure = new Failure();
        $failure->setMessage('vanished');
        $failure->setNexusHandlerFailureInfo($info);

        $exception = FailureConverter::mapFailureToException($failure, DataConverter::createDefault());

        self::assertInstanceOf(NexusHandlerFailure::class, $exception);
        self::assertSame(ErrorType::NotFound, $exception->getErrorType());
        self::assertSame(RetryBehavior::NonRetryable, $exception->getRetryBehaviorEnum());
    }
}
