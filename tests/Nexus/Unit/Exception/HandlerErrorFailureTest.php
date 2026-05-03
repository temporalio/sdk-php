<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Exception;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerErrorFailure;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Exception\RetryBehavior;
use Temporal\Nexus\FailureInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerErrorFailure::class)]
#[UsesClass(HandlerException::class)]
#[UsesClass(NexusException::class)]
#[UsesClass(FailureInfo::class)]
#[UsesClass(ErrorType::class)]
#[UsesClass(RetryBehavior::class)]
final class HandlerErrorFailureTest extends TestCase
{
    public function testFromShapesCanonicalFailure(): void
    {
        $e = HandlerException::create(ErrorType::Internal, 'boom');

        $failure = HandlerErrorFailure::from($e);

        self::assertSame('boom', $failure->message);
        self::assertSame(['type' => 'nexus.HandlerError'], $failure->metadata);
        self::assertSame(['type' => 'INTERNAL'], \json_decode($failure->detailsJson, true));
    }

    public function testFromOmitsRetryableOverrideWhenUnspecified(): void
    {
        $e = HandlerException::create(ErrorType::BadRequest, 'x');

        $details = \json_decode(HandlerErrorFailure::from($e)->detailsJson, true);

        self::assertArrayNotHasKey('retryableOverride', $details);
    }

    public function testFromEmitsRetryableTrue(): void
    {
        $e = HandlerException::create(
            ErrorType::Internal,
            'x',
            retryBehavior: RetryBehavior::Retryable,
        );

        $details = \json_decode(HandlerErrorFailure::from($e)->detailsJson, true);

        self::assertTrue($details['retryableOverride']);
    }

    public function testFromEmitsRetryableFalse(): void
    {
        $e = HandlerException::create(
            ErrorType::Internal,
            'x',
            retryBehavior: RetryBehavior::NonRetryable,
        );

        $details = \json_decode(HandlerErrorFailure::from($e)->detailsJson, true);

        self::assertFalse($details['retryableOverride']);
    }

    public function testFromPreservesUnknownRawErrorType(): void
    {
        $e = HandlerException::fromRawType('FUTURE_SPEC_VALUE', 'x');

        $details = \json_decode(HandlerErrorFailure::from($e)->detailsJson, true);

        self::assertSame('FUTURE_SPEC_VALUE', $details['type']);
    }

    public function testFromPreservesCauseChain(): void
    {
        $cause = new \RuntimeException('upstream', previous: new \LogicException('root'));
        $e = HandlerException::create(ErrorType::Internal, 'boom', $cause);

        $failure = HandlerErrorFailure::from($e);

        self::assertNotNull($failure->cause);
        self::assertSame('upstream', $failure->cause->message);
        self::assertSame('root', $failure->cause->cause?->message);
    }

    public function testFromMergesExtraDetails(): void
    {
        $e = HandlerException::create(ErrorType::Internal, 'x');

        $details = \json_decode(
            HandlerErrorFailure::from($e, ['traceId' => 'abc', 'attempt' => 3])->detailsJson,
            true,
        );

        self::assertSame('INTERNAL', $details['type']);
        self::assertSame('abc', $details['traceId']);
        self::assertSame(3, $details['attempt']);
    }

    public function testFromIgnoresExtraTypeAndRetryableOverride(): void
    {
        $e = HandlerException::create(
            ErrorType::Internal,
            'x',
            retryBehavior: RetryBehavior::Retryable,
        );

        $details = \json_decode(
            HandlerErrorFailure::from($e, [
                'type' => 'BAD_REQUEST',
                'retryableOverride' => false,
            ])->detailsJson,
            true,
        );

        self::assertSame('INTERNAL', $details['type']);
        self::assertTrue($details['retryableOverride']);
    }

    public function testIsHandlerErrorRecognizesMarker(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::create(ErrorType::Internal, 'x'));

        self::assertTrue(HandlerErrorFailure::isHandlerError($failure));
    }

    public function testIsHandlerErrorRejectsForeignFailure(): void
    {
        self::assertFalse(HandlerErrorFailure::isHandlerError(
            new FailureInfo(message: 'x', metadata: ['type' => 'nexus.OperationError']),
        ));
    }

    public function testReadErrorTypeReturnsEnum(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::create(ErrorType::NotFound, 'x'));

        self::assertSame(ErrorType::NotFound, HandlerErrorFailure::readErrorType($failure));
    }

    public function testReadErrorTypeReturnsUnknownForUnrecognized(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::fromRawType('FUTURE', 'x'));

        self::assertSame(ErrorType::Unknown, HandlerErrorFailure::readErrorType($failure));
    }

    public function testReadRawErrorTypePreservesUnknown(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::fromRawType('FUTURE', 'x'));

        self::assertSame('FUTURE', HandlerErrorFailure::readRawErrorType($failure));
    }

    public function testReadErrorTypeReturnsNullForNonHandlerError(): void
    {
        self::assertNull(HandlerErrorFailure::readErrorType(new FailureInfo('x')));
    }

    public function testReadRetryableOverrideReadsTrue(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::create(
            ErrorType::Internal,
            'x',
            retryBehavior: RetryBehavior::Retryable,
        ));

        self::assertTrue(HandlerErrorFailure::readRetryableOverride($failure));
    }

    public function testReadRetryableOverrideReadsFalse(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::create(
            ErrorType::Internal,
            'x',
            retryBehavior: RetryBehavior::NonRetryable,
        ));

        self::assertFalse(HandlerErrorFailure::readRetryableOverride($failure));
    }

    public function testReadRetryableOverrideReturnsNullWhenAbsent(): void
    {
        $failure = HandlerErrorFailure::from(HandlerException::create(ErrorType::Internal, 'x'));

        self::assertNull(HandlerErrorFailure::readRetryableOverride($failure));
    }

    public function testReadRetryableOverrideRejectsNonBoolValue(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.HandlerError'],
            detailsJson: \json_encode(['type' => 'INTERNAL', 'retryableOverride' => 'yes']),
        );

        self::assertNull(HandlerErrorFailure::readRetryableOverride($failure));
    }

    public function testReadHandlesMalformedDetailsJson(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.HandlerError'],
            detailsJson: '{not valid',
        );

        self::assertNull(HandlerErrorFailure::readErrorType($failure));
        self::assertNull(HandlerErrorFailure::readRetryableOverride($failure));
    }
}
