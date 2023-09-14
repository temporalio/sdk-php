<?php

declare(strict_types=1);

namespace Promise;

use React\Promise\Deferred;
use Temporal\Promise;
use Temporal\Tests\Unit\Promise\BaseFunction;

/**
 * Got from reactphp/promise and modified
 * @license MIT
 * @link https://github.com/reactphp/promise/blob/f913fb8cceba1e6644b7b90c4bfb678ed8a3ef38/tests/FunctionAllTest.php
 */
final class FunctionAllTestCase extends BaseFunction
{
    public function testResolveEmptyInput(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([]));

        Promise::all([])
            ->then($mock);
    }

    public function testResolveValuesArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        Promise::all([1, 2, 3])
            ->then($mock);
    }

    public function testResolvePromisesArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        Promise::all([Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)])
            ->then($mock);
    }

    public function testResolveSparseArrayInput(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([null, 1, null, 1, 1]));

        Promise::all([null, 1, null, 1, 1])
            ->then($mock);
    }

    public function testRejectIfAnyInputPromiseRejects(): void
    {
        $e = new \Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($e));

        Promise::all([Promise::resolve(1), Promise::reject($e), Promise::resolve(3)])
            ->then($this->expectCallableNever(), $mock);
    }

    public function testAcceptAPromiseForAnArray(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        Promise::all([Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)])
            ->then($mock);
    }

    public function testPreserveTheOrderOfArrayWhenResolvingAsyncPromises(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        $deferred = new Deferred();

        Promise::all([Promise::resolve(1), $deferred->promise(), Promise::resolve(3)])
            ->then($mock);

        $deferred->resolve(2);
    }
}
