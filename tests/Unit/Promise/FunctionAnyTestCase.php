<?php

declare(strict_types=1);

namespace Promise;

use React\Promise\Deferred;
use React\Promise\Exception\LengthException;
use Temporal\Internal\Promise\Reasons;
use Temporal\Promise;
use Temporal\Tests\Unit\Promise\BaseFunction;

/**
 * Got from reactphp/promise and modified
 * @license MIT
 * @link https://github.com/reactphp/promise/blob/f913fb8cceba1e6644b7b90c4bfb678ed8a3ef38/tests/FunctionAnyTest.php
 */
final class FunctionAnyTestCase extends BaseFunction
{
    public function testRejectWithLengthExceptionWithEmptyInputArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->callback(function ($exception) {
                    return $exception instanceof LengthException &&
                        'Input array must contain at least 1 item but contains only 0 items.' === $exception->getMessage();
                })
            );

        Promise::any([])
            ->then($this->expectCallableNever(), $mock);
    }

    public function testResolveWithAnInputValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        Promise::any([1, 2, 3])
            ->then($mock);
    }

    public function testResolveWithAPromisedInputValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        Promise::any([Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)])
            ->then($mock);
    }

    public function testRejectWithAllRejectedInputValuesIfAllInputsAreRejected(): void
    {
        $e1 = new \Exception();
        $e2 = new \Exception();
        $e3 = new \Exception();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(fn (Reasons $e): bool => \iterator_to_array($e) === [0 => $e1, 1 => $e2, 2 => $e3]));

        Promise::any([Promise::reject($e1), Promise::reject($e2), Promise::reject($e3)])
            ->then($this->expectCallableNever(), $mock);
    }

    public function testResolveWhenFirstInputPromiseResolves(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        Promise::any([Promise::resolve(1), Promise::reject(new \Exception()), Promise::reject(new \Exception())])
            ->then($mock);
    }

    public function testNotRelyOnArryIndexesWhenUnwrappingToASingleResolutionValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d1 = new Deferred();
        $d2 = new Deferred();

        Promise::any(['abc' => $d1->promise(), 1 => $d2->promise()])
            ->then($mock);

        $d2->resolve(2);
        $d1->resolve(1);
    }

    public function testRejectWhenInputPromiseRejects(): void
    {
        $e = new \Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            // ->with($this->identicalTo(null));
            ->with($this->callback(fn (Reasons $reason): bool => \iterator_to_array($reason) === [$e]));

        Promise::any([Promise::reject($e)])
            ->then($this->expectCallableNever(), $mock)
            ->then(null, fn(\Throwable $e) => null);
    }

    public function testCancelInputPromise(): void
    {
        $mock = $this->creteCancellableMock();
        $mock
            ->expects($this->once())
            ->method('cancel');

        Promise::any([$mock])->cancel();
    }

    public function testCancelInputArrayPromises(): void
    {
        $mock1 = $this->creteCancellableMock();
        $mock1
            ->expects($this->once())
            ->method('cancel');

        $mock2 = $this->creteCancellableMock();
        $mock2
            ->expects($this->once())
            ->method('cancel');

        Promise::any([$mock1, $mock2])->cancel();
    }

    public function testNotCancelOtherPendingInputArrayPromisesIfOnePromiseFulfills(): void
    {
        $e = new \Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');


        $deferred = new Deferred($mock);
        $deferred->resolve($e);

        $mock2 = $this->creteCancellableMock();
        $mock2
            ->expects($this->never())
            ->method('cancel');

        Promise::some([$deferred->promise(), $mock2], 1)->cancel();
    }
}
