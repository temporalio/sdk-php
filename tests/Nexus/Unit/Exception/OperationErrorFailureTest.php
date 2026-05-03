<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Exception;

use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Exception\OperationErrorFailure;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\FailureInfo;
use Temporal\Nexus\OperationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationErrorFailure::class)]
#[UsesClass(OperationException::class)]
#[UsesClass(NexusException::class)]
#[UsesClass(FailureInfo::class)]
final class OperationErrorFailureTest extends TestCase
{
    public function testFromFailedExceptionShapesCanonicalFailure(): void
    {
        $e = OperationException::failed('boom');

        $failure = OperationErrorFailure::from($e);

        self::assertSame('boom', $failure->message);
        self::assertSame(['type' => 'nexus.OperationError'], $failure->metadata);
        self::assertNotNull($failure->detailsJson);

        $details = \json_decode($failure->detailsJson, true);
        self::assertSame(['state' => 'failed'], $details);
    }

    public function testFromCanceledExceptionUsesCanceledState(): void
    {
        $e = OperationException::canceled('user requested');

        $failure = OperationErrorFailure::from($e);

        $details = \json_decode($failure->detailsJson, true);
        self::assertSame('canceled', $details['state']);
    }

    public function testFromPreservesCauseChain(): void
    {
        $cause = new \RuntimeException('upstream', previous: new \LogicException('root'));
        $e = OperationException::failed('boom', $cause);

        $failure = OperationErrorFailure::from($e);

        self::assertNotNull($failure->cause);
        self::assertSame('upstream', $failure->cause->message);
        self::assertNotNull($failure->cause->cause);
        self::assertSame('root', $failure->cause->cause->message);
    }

    public function testFromMergesExtraDetails(): void
    {
        $e = OperationException::failed('x');

        $failure = OperationErrorFailure::from($e, ['code' => 42, 'reason' => 'quota']);

        $details = \json_decode($failure->detailsJson, true);
        self::assertSame('failed', $details['state']);
        self::assertSame(42, $details['code']);
        self::assertSame('quota', $details['reason']);
    }

    public function testFromIgnoresExtraStateOverride(): void
    {
        $e = OperationException::canceled('x');

        $failure = OperationErrorFailure::from($e, ['state' => 'failed']);

        $details = \json_decode($failure->detailsJson, true);
        self::assertSame('canceled', $details['state']);
    }

    public function testIsOperationErrorRecognizesMarker(): void
    {
        $failure = OperationErrorFailure::from(OperationException::failed('x'));

        self::assertTrue(OperationErrorFailure::isOperationError($failure));
    }

    public function testIsOperationErrorRejectsForeignFailure(): void
    {
        $foreign = new FailureInfo(message: 'x', metadata: ['type' => 'something.else']);

        self::assertFalse(OperationErrorFailure::isOperationError($foreign));
    }

    public function testIsOperationErrorRejectsBareFailure(): void
    {
        self::assertFalse(OperationErrorFailure::isOperationError(new FailureInfo('x')));
    }

    public function testReadStateReturnsFailed(): void
    {
        $failure = OperationErrorFailure::from(OperationException::failed('x'));

        self::assertSame(OperationState::Failed, OperationErrorFailure::readState($failure));
    }

    public function testReadStateReturnsCanceled(): void
    {
        $failure = OperationErrorFailure::from(OperationException::canceled('x'));

        self::assertSame(OperationState::Canceled, OperationErrorFailure::readState($failure));
    }

    public function testReadStateRejectsNonOperationError(): void
    {
        self::assertNull(OperationErrorFailure::readState(new FailureInfo('x')));
    }

    public function testReadStateRejectsUnknownStateValue(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.OperationError'],
            detailsJson: \json_encode(['state' => 'running']),
        );

        self::assertNull(OperationErrorFailure::readState($failure));
    }

    public function testReadStateRejectsMissingDetailsJson(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.OperationError'],
        );

        self::assertNull(OperationErrorFailure::readState($failure));
    }

    public function testReadStateRejectsScalarDetailsJson(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.OperationError'],
            detailsJson: '"just a string"',
        );

        self::assertNull(OperationErrorFailure::readState($failure));
    }

    public function testReadStateRejectsNonStringStateField(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.OperationError'],
            detailsJson: \json_encode(['state' => 42]),
        );

        self::assertNull(OperationErrorFailure::readState($failure));
    }

    public function testReadStateRejectsMalformedDetailsJson(): void
    {
        $failure = new FailureInfo(
            message: 'x',
            metadata: ['type' => 'nexus.OperationError'],
            detailsJson: '{not valid json',
        );

        self::assertNull(OperationErrorFailure::readState($failure));
    }
}
