<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Promise;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;

/**
 * Got from reactphp/promise and modified
 * @link https://github.com/reactphp/promise/blob/f913fb8cceba1e6644b7b90c4bfb678ed8a3ef38/tests/TestCase.php
 * @license MIT
 */
abstract class BaseFunction extends TestCase
{
    protected function expectCallableExactly($amount)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->exactly($amount))
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableNever(): callable
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        return $this->getMockBuilder('stdClass')->addMethods(array('__invoke'))->getMock();
    }

    protected function setExpectedException($exception, $exceptionMessage = '', $exceptionCode = null)
    {
        $this->expectException($exception);
        if ($exceptionMessage !== '') {
            $this->expectExceptionMessage($exceptionMessage);
        }
        if ($exceptionCode !== null) {
            $this->expectExceptionCode($exceptionCode);
        }
    }

    protected function creteCancellableMock(): MockObject
    {
        return \interface_exists(CancellablePromiseInterface::class)
            ? $this
                ->getMockBuilder(CancellablePromiseInterface::class)
                ->getMock()
            : $this
                ->getMockBuilder(PromiseInterface::class)
                ->getMock();
    }
}
