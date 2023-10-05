<?php

declare(strict_types=1);

namespace Promise;

use Exception;
use React\Promise\Deferred;
use React\Promise\Exception\LengthException;
use Temporal\Internal\Promise\Reasons;
use Temporal\Promise;
use Temporal\Tests\Unit\Promise\BaseFunction;

/**
 * Got from reactphp/promise and modified
 * @license MIT
 * @link https://github.com/reactphp/promise/blob/f913fb8cceba1e6644b7b90c4bfb678ed8a3ef38/tests/FunctionSomeTest.php
 */
final class FunctionSomeTestCase extends BaseFunction
{
    public function testRejectWithLengthExceptionWithEmptyInputArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->callback(static function ($exception): bool {
                    return $exception instanceof LengthException &&
                        'Input array must contain at least 1 item but contains only 0 items.' === $exception->getMessage();
                })
            );

        Promise::some([], 1)
            ->then($this->expectCallableNever(), $mock);
    }

    public function testRejectWithLengthExceptionWithInputArrayContainingNotEnoughItems(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->callback(function ($exception) {
                    return $exception instanceof LengthException &&
                        'Input array must contain at least 4 items but contains only 3 items.' === $exception->getMessage();
                })
            );

        Promise::some(
            [1, 2, 3],
            4
        )->then($this->expectCallableNever(), $mock);
    }

    public function testResolveValuesArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2]));

        Promise::some(
            [1, 2, 3],
            2
        )->then($mock);
    }

    public function testResolvePromisesArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2]));

        Promise::some(
            [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)],
            2
        )->then($mock);
    }

    public function testResolveSparseArrayInput(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([null, 1]));

        Promise::some(
            [null, 1, null, 2, 3],
            2
        )->then($mock);
    }

    public function testRejectIfAnyInputPromiseRejectsBeforeDesiredNumberOfInputsAreResolved(): void
    {
        $e = new Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (mixed $exception) use ($e) {
                return $exception instanceof Reasons &&
                    \in_array($e, \iterator_to_array($exception));
            }));

        Promise::some(
            [Promise::resolve(1), Promise::reject($e), Promise::reject($e)],
            2
        )->then($this->expectCallableNever(), $mock);
    }

    public function testResolveWithEmptyArrayIfHowManyIsLessThanOne(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([]));

        Promise::some(
            [1],
            0
        )->then($mock);
    }

    public function testCancelInputArrayPromises(): void
    {
        $mock1 = $this
            ->creteCancellableMock();
        $mock1
            ->expects($this->once())
            ->method('cancel');

        $mock2 = $this
            ->creteCancellableMock();
        $mock2
            ->expects($this->once())
            ->method('cancel');

        Promise::some([$mock1, $mock2], 1)->cancel();
    }

    public function testNotCancelOtherPendingInputArrayPromisesIfEnoughPromisesFulfill(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        $deferred = new Deferred($mock);
        $deferred->resolve(null);

        $mock2 = $this
            ->creteCancellableMock();
        $mock2
            ->expects($this->never())
            ->method('cancel');

        Promise::some([$deferred->promise(), $mock2], 1);
    }

    public function testNotCancelOtherPendingInputArrayPromisesIfEnoughPromisesReject(): void
    {
        $e = new Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        $deferred = new Deferred($mock);
        $deferred->reject($e);

        $mock2 = $this
            ->creteCancellableMock();
        $mock2
            ->expects($this->never())
            ->method('cancel');

        Promise::some([$deferred->promise(), $mock2], 2);
    }
}
