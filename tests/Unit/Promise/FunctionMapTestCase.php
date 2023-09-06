<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Promise;

use Exception;
use React\Promise\Deferred;
use Temporal\Promise;

/**
 * Got from reactphp/promise and modified
 * @license MIT
 * @link https://github.com/reactphp/promise/blob/f913fb8cceba1e6644b7b90c4bfb678ed8a3ef38/tests/FunctionMapTest.php
 */
final class FunctionMapTestCase extends BaseFunction
{
    public function testMapInputValuesArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([2, 4, 6]));

        Promise::map(
            [1, 2, 3],
            $this->mapper()
        )->then($mock);
    }

    public function testMapInputPromisesArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([2, 4, 6]));

        Promise::map(
            [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)],
            $this->mapper()
        )->then($mock);
    }

    public function testMapMixedInputArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([2, 4, 6]));

        Promise::map(
            [1, Promise::resolve(2), 3],
            $this->mapper()
        )->then($mock);
    }

    public function testMapInputWhenMapperReturnsAPromise(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([2, 4, 6]));

        Promise::map(
            [1, 2, 3],
            $this->promiseMapper()
        )->then($mock);
    }

    public function testPreserveTheOrderOfArrayWhenResolvingAsyncPromises(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([2, 4, 6]));

        $deferred = new Deferred();

        Promise::map(
            [Promise::resolve(1), $deferred->promise(), Promise::resolve(3)],
            $this->mapper()
        )->then($mock);

        $deferred->resolve(2);
    }

    public function testRejectWhenInputContainsRejection(): void
    {
        $e = new Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($e));

        Promise::map(
            [Promise::resolve(1), Promise::reject($e), Promise::resolve(3)],
            $this->mapper()
        )->then($this->expectCallableNever(), $mock);
    }

    public function testRejectWhenInputPromiseRejects(): void
    {
        $e = new Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($e));

        Promise::map(
            [Promise::reject($e)],
            $this->mapper()
        )->then($this->expectCallableNever(), $mock);
    }

    public function testCancelInputPromise(): void
    {
        $mock = $this->creteCancellableMock();
        $mock
            ->expects($this->once())
            ->method('cancel');

        Promise::map(
            [$mock],
            $this->mapper()
        )->cancel();
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

        Promise::map(
            [$mock1, $mock2],
            $this->mapper()
        )->cancel();
    }

    protected function mapper(): callable
    {
        return static fn($val) => $val * 2;
    }

    protected function promiseMapper(): callable
    {
        return static fn($val) => Promise::resolve($val * 2);
    }
}
