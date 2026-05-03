<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\OperationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationException::class)]
final class OperationExceptionTest extends TestCase
{
    public function testFailureWithMessage(): void
    {
        $ex = OperationException::failed('Test failure');

        self::assertSame('Test failure', $ex->getMessage());
        self::assertNull($ex->getPrevious());
        self::assertSame(OperationState::Failed, $ex->state);
    }

    public function testFailureWithCause(): void
    {
        $cause = new \RuntimeException('Root cause');
        $ex = OperationException::failedFromCause($cause);

        self::assertSame($cause, $ex->getPrevious());
        self::assertSame(OperationState::Failed, $ex->state);
    }

    public function testFailureWithMessageAndCause(): void
    {
        $cause = new \RuntimeException('Root cause');
        $ex = OperationException::failed('Custom message', $cause);

        self::assertSame('Custom message', $ex->getMessage());
        self::assertSame($cause, $ex->getPrevious());
        self::assertSame(OperationState::Failed, $ex->state);
    }

    public function testCanceledWithMessage(): void
    {
        $ex = OperationException::canceled('Test cancellation');

        self::assertSame('Test cancellation', $ex->getMessage());
        self::assertNull($ex->getPrevious());
        self::assertSame(OperationState::Canceled, $ex->state);
    }

    public function testCanceledWithCause(): void
    {
        $cause = new \RuntimeException('Cancellation reason');
        $ex = OperationException::canceledFromCause($cause);

        self::assertSame($cause, $ex->getPrevious());
        self::assertSame(OperationState::Canceled, $ex->state);
    }

    public function testCanceledWithMessageAndCause(): void
    {
        $cause = new \RuntimeException('Cancellation reason');
        $ex = OperationException::canceled('Custom cancellation message', $cause);

        self::assertSame('Custom cancellation message', $ex->getMessage());
        self::assertSame($cause, $ex->getPrevious());
        self::assertSame(OperationState::Canceled, $ex->state);
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \Exception('Root cause');
        $intermediateCause = new \RuntimeException('Intermediate', 0, $rootCause);
        $ex = OperationException::failed('Operation failed', $intermediateCause);

        self::assertSame('Operation failed', $ex->getMessage());
        self::assertSame($intermediateCause, $ex->getPrevious());
        self::assertSame($rootCause, $ex->getPrevious()->getPrevious());
        self::assertSame(OperationState::Failed, $ex->state);
    }
}
