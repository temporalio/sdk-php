<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Exception\RetryBehavior;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerException::class)]
final class HandlerExceptionTest extends TestCase
{
    public function testCreateWithMessage(): void
    {
        $ex = HandlerException::create(ErrorType::BadRequest, 'Invalid input');

        self::assertSame('Invalid input', $ex->getMessage());
        self::assertSame(ErrorType::BadRequest, $ex->errorType);
        self::assertSame('BAD_REQUEST', $ex->rawErrorType);
        self::assertNull($ex->getPrevious());
        self::assertSame(RetryBehavior::Unspecified, $ex->retryBehavior);
    }

    public function testCreateWithCause(): void
    {
        $cause = new \RuntimeException('Root cause');
        $ex = HandlerException::create(ErrorType::Internal, 'Handler error', $cause);

        self::assertSame('Handler error', $ex->getMessage());
        self::assertSame($cause, $ex->getPrevious());
        self::assertSame(ErrorType::Internal, $ex->errorType);
    }

    public function testCreateWithRetryBehavior(): void
    {
        $ex = HandlerException::create(
            ErrorType::Internal,
            'Server error',
            retryBehavior: RetryBehavior::NonRetryable,
        );

        self::assertSame(RetryBehavior::NonRetryable, $ex->retryBehavior);
        self::assertFalse($ex->isRetryable());
    }

    public function testFromCauseBuildsMessage(): void
    {
        $ex = HandlerException::fromCause(ErrorType::Internal, new \RuntimeException('boom'));

        self::assertSame('handler error: boom', $ex->getMessage());
        self::assertSame(ErrorType::Internal, $ex->errorType);
        self::assertInstanceOf(\RuntimeException::class, $ex->getPrevious());
        self::assertSame('boom', $ex->getPrevious()->getMessage());
    }

    public function testFromCauseWithEmptyMessageDefaultsToHandlerError(): void
    {
        $ex = HandlerException::fromCause(ErrorType::Internal, new \RuntimeException(''));

        self::assertSame('handler error', $ex->getMessage());
    }

    public function testFromCauseWithRetryBehavior(): void
    {
        $cause = new \RuntimeException('Cause');
        $ex = HandlerException::fromCause(
            ErrorType::BadRequest,
            $cause,
            RetryBehavior::Retryable,
        );

        self::assertSame($cause, $ex->getPrevious());
        self::assertSame(RetryBehavior::Retryable, $ex->retryBehavior);
        self::assertTrue($ex->isRetryable());
    }

    public function testFromRawTypeUnknownYieldsUnknown(): void
    {
        $ex = HandlerException::fromRawType('COMPLETELY_NEW', 'msg');

        self::assertSame('COMPLETELY_NEW', $ex->rawErrorType);
        self::assertSame(ErrorType::Unknown, $ex->errorType);
        self::assertSame('msg', $ex->getMessage());
    }

    public function testFromRawTypeKnownResolvesEnum(): void
    {
        $ex = HandlerException::fromRawType('BAD_REQUEST', 'msg');

        self::assertSame('BAD_REQUEST', $ex->rawErrorType);
        self::assertSame(ErrorType::BadRequest, $ex->errorType);
    }

    public function testIsRetryableFromErrorType(): void
    {
        $retryable = [
            ErrorType::RequestTimeout,
            ErrorType::ResourceExhausted,
            ErrorType::Internal,
            ErrorType::Unavailable,
            ErrorType::UpstreamTimeout,
            ErrorType::Unknown,
        ];
        foreach ($retryable as $type) {
            $ex = HandlerException::create($type, 'x');
            self::assertTrue($ex->isRetryable(), "{$type->value} should be retryable");
        }

        $nonRetryable = [
            ErrorType::BadRequest,
            ErrorType::Unauthenticated,
            ErrorType::Unauthorized,
            ErrorType::NotFound,
            ErrorType::Conflict,
            ErrorType::NotImplemented,
        ];
        foreach ($nonRetryable as $type) {
            $ex = HandlerException::create($type, 'x');
            self::assertFalse($ex->isRetryable(), "{$type->value} should be non-retryable");
        }

        // Completeness guard — every ErrorType case must appear above. New
        // cases shipping without an explicit retryability classification will
        // trip this assertion.
        $covered = \count($retryable) + \count($nonRetryable);
        self::assertSame(\count(ErrorType::cases()), $covered);
    }

    public function testIsRetryableOverriddenByRetryable(): void
    {
        // BAD_REQUEST is non-retryable by default, but explicit Retryable flips it.
        $ex = HandlerException::create(
            ErrorType::BadRequest,
            'x',
            retryBehavior: RetryBehavior::Retryable,
        );
        self::assertTrue($ex->isRetryable());
    }

    public function testIsRetryableOverriddenByNonRetryable(): void
    {
        // INTERNAL is retryable by default, but explicit NonRetryable flips it.
        $ex = HandlerException::create(
            ErrorType::Internal,
            'x',
            retryBehavior: RetryBehavior::NonRetryable,
        );
        self::assertFalse($ex->isRetryable());
    }

    public function testIsInstanceOfNexusException(): void
    {
        $ex = HandlerException::create(ErrorType::Internal, 'x');
        self::assertInstanceOf(NexusException::class, $ex);
        self::assertInstanceOf(\RuntimeException::class, $ex);
    }

    public function testConstructorIsPrivate(): void
    {
        $reflection = new \ReflectionClass(HandlerException::class);
        self::assertTrue($reflection->getConstructor()->isPrivate());
    }
}
