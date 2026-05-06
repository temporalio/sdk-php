<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Api\Nexus\V1\Failure;
use Temporal\Api\Nexus\V1\HandlerError;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusHandlerErrorException::class)]
final class NexusHandlerErrorExceptionTestCase extends AbstractUnit
{
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
