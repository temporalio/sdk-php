<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Api\Nexus\V1\Failure;
use Temporal\Api\Nexus\V1\HandlerError;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class NexusHandlerErrorExceptionTestCase extends AbstractUnit
{
    public function testConstructionWithFailure(): void
    {
        $failure = new Failure();
        $failure->setMessage('Something broke');

        $handlerError = new HandlerError();
        $handlerError->setErrorType('INTERNAL');
        $handlerError->setFailure($failure);

        $exception = new NexusHandlerErrorException($handlerError);

        self::assertSame('Something broke', $exception->getMessage());
        self::assertSame($handlerError, $exception->handlerError);
        self::assertNull($exception->getPrevious());
    }

    public function testConstructionWithPreviousException(): void
    {
        $cause = new \RuntimeException('root cause');

        $failure = new Failure();
        $failure->setMessage('Handler failed');

        $handlerError = new HandlerError();
        $handlerError->setErrorType('UNAVAILABLE');
        $handlerError->setFailure($failure);

        $exception = new NexusHandlerErrorException($handlerError, $cause);

        self::assertSame('Handler failed', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }

    public function testConstructionWithoutFailure(): void
    {
        $handlerError = new HandlerError();
        $handlerError->setErrorType('INTERNAL');

        $exception = new NexusHandlerErrorException($handlerError);

        self::assertSame('handler error', $exception->getMessage());
    }
}
