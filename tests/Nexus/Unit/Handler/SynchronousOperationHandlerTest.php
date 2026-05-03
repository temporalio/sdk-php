<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\LogicException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\SyncOperationStartResult;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Tests\Nexus\Fixture\Function\UpperCaseFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SynchronousOperationHandler::class)]
final class SynchronousOperationHandlerTest extends TestCase
{
    public function testStart(): void
    {
        $handler = new SynchronousOperationHandler(
            fn($ctx, $details, $param) => "result-{$param}",
        );

        $result = $handler->start(
            new OperationContext(service: 's', operation: 'op'),
            new OperationStartDetails(requestId: 'r1'),
            'input',
        );

        self::assertInstanceOf(SyncOperationStartResult::class, $result);
        self::assertSame('result-input', $result->value);
    }

    public function testCancelThrows(): void
    {
        $handler = new SynchronousOperationHandler(
            fn($ctx, $details, $param) => null,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cancel() is not supported on synchronous operations');
        $handler->cancel(
            new OperationContext(service: 's', operation: 'op'),
            new OperationCancelDetails(operationToken: 'token'),
        );
    }

    public function testFromCallableFactory(): void
    {
        $handler = SynchronousOperationHandler::fromCallable(
            fn($ctx, $details, $param) => 42,
        );

        self::assertInstanceOf(SynchronousOperationHandler::class, $handler);
    }

    public function testAcceptsSynchronousOperationFunctionInterface(): void
    {
        $handler = SynchronousOperationHandler::fromFunction(new UpperCaseFunction());

        $result = $handler->start(
            new OperationContext(service: 's', operation: 'op'),
            new OperationStartDetails(requestId: 'r1'),
            'hello',
        );

        self::assertInstanceOf(SyncOperationStartResult::class, $result);
        self::assertSame('HELLO', $result->value);
    }

    public function testFromCallableStillWorks(): void
    {
        $handler = SynchronousOperationHandler::fromCallable(
            fn($ctx, $details, $input) => $input . '!',
        );

        $result = $handler->start(
            new OperationContext(service: 's', operation: 'op'),
            new OperationStartDetails(requestId: 'r1'),
            'x',
        );

        self::assertSame('x!', $result->value);
    }

    public function testGetFunctionExposesWrappedFunctor(): void
    {
        $functor = new UpperCaseFunction();
        $handler = SynchronousOperationHandler::fromFunction($functor);

        self::assertSame($functor, $handler->getFunction());
    }

    public function testConstructorAcceptsBothCallableAndInterface(): void
    {
        $h1 = new SynchronousOperationHandler(fn() => 'ok');
        $h2 = new SynchronousOperationHandler(new UpperCaseFunction());

        self::assertInstanceOf(SynchronousOperationHandler::class, $h1);
        self::assertInstanceOf(SynchronousOperationHandler::class, $h2);
    }
}
