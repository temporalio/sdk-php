<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Promise;

use Exception;
use React\Promise\Deferred;
use Temporal\Promise;

/**
 * Got from reactphp/promise and modified
 * @license MIT
 * @link https://github.com/reactphp/promise/blob/f913fb8cceba1e6644b7b90c4bfb678ed8a3ef38/tests/FunctionReduceTest.php
 */
final class FunctionReduceTestCase extends BaseFunction
{
    public function testReduceValuesWithoutInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(6));

        Promise::reduce(
            [1, 2, 3],
            $this->plus()
        )->then($mock);
    }

    public function testReduceValuesWithInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        Promise::reduce(
            [1, 2, 3],
            $this->plus(),
            1
        )->then($mock);
    }

    public function testReduceValuesWithInitialPromise(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        Promise::reduce(
            [1, 2, 3],
            $this->plus(),
            Promise::resolve(1)
        )->then($mock);
    }

    public function testReducePromisedValuesWithoutInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(6));

        Promise::reduce(
            [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)],
            $this->plus()
        )->then($mock);
    }

    public function testReducePromisedValuesWithInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        Promise::reduce(
            [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)],
            $this->plus(),
            1
        )->then($mock);
    }

    public function testReducePromisedValuesWithInitialPromise(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        Promise::reduce(
            [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)],
            $this->plus(),
            Promise::resolve(1)
        )->then($mock);
    }

    public function testReduceEmptyInputWithInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        Promise::reduce(
            [],
            $this->plus(),
            1
        )->then($mock);
    }

    public function testReduceEmptyInputWithInitialPromise(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        Promise::reduce(
            [],
            $this->plus(),
            Promise::resolve(1)
        )->then($mock);
    }

    public function testRejectWhenInputContainsRejection(): void
    {
        $e = new Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($e));

        Promise::reduce(
            [Promise::resolve(1), Promise::reject($e), Promise::resolve(3)],
            $this->plus(),
            Promise::resolve(1)
        )->then($this->expectCallableNever(), $mock);
    }

    public function testResolveWithNullWhenInputIsEmptyAndNoInitialValueOrPromiseProvided(): void
    {
        // Note: this is different from when.js's behavior!
        // In when.Promise::reduce(), this rejects with a TypeError exception (following
        // JavaScript's [].reduce behavior.
        // We're following PHP's array_reduce behavior and resolve with NULL.
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(null));

        Promise::reduce(
            [],
            $this->plus()
        )->then($mock);
    }

    public function testAllowSparseArrayInputWithoutInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(3));

        Promise::reduce(
            [null, null, 1, null, 1, 1],
            $this->plus()
        )->then($mock);
    }

    public function testAllowSparseArrayInputWithInitialValue(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(4));

        Promise::reduce(
            [null, null, 1, null, 1, 1],
            $this->plus(),
            1
        )->then($mock);
    }

    public function testReduceInInputOrder(): void
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo('123'));

        Promise::reduce(
            [1, 2, 3],
            $this->append(),
            ''
        )->then($mock);
    }

    public function testProvideCorrectBasisValue(): void
    {
        $insertIntoArray = function ($arr, $val, $i) {
            $arr[$i] = $val;

            return $arr;
        };

        $d1 = new Deferred();
        $d2 = new Deferred();
        $d3 = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        Promise::reduce(
            [$d1->promise(), $d2->promise(), $d3->promise()],
            $insertIntoArray,
            []
        )->then($mock);

        $d3->resolve(3);
        $d1->resolve(1);
        $d2->resolve(2);
    }

    public function testRejectWhenInputPromiseRejects(): void
    {
        $e = new Exception();
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($e));

        Promise::reduce(
            [Promise::reject($e)],
            $this->plus(),
            1
        )->then($this->expectCallableNever(), $mock);
    }

    public function testCancelInputPromise(): void
    {
        $mock = $this->creteCancellableMock();
        $mock
            ->expects($this->once())
            ->method('cancel');

        Promise::reduce(
            [$mock],
            $this->plus(),
            1
        )->cancel();
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

        Promise::reduce(
            [$mock1, $mock2],
            $this->plus(),
            1
        )->cancel();
    }

    protected function plus(): callable
    {
        return static fn($sum, $val) => $sum + $val;
    }

    protected function append(): callable
    {
        return static fn($sum, $val) => $sum . $val;
    }
}
