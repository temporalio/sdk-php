<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Worker;

use Temporal\Client\Worker\Autowired;

function global_function(): int
{
    return 0xDEAD_BEEF;
}

class AutowiringTestCase extends WorkerTestCase
{
    public static function staticMethod(): int
    {
        return global_function();
    }

    public function reflectionDataProvider(): array
    {
        return [
            // Closure
            'closure'                                 => [new \ReflectionFunction(fn() => $this->instanceMethod())],
            // Static Closure
            'static closure'                          => [new \ReflectionFunction(static fn() => global_function())],
            // Instance Method
            static::class . '::instanceMethod' => [new \ReflectionMethod($this, 'instanceMethod')],
            // Static Method
            static::class . '::staticMethod'   => [new \ReflectionMethod(static::class . '::staticMethod')],
            // Function
            __NAMESPACE__ . '\\global_function'               => [new \ReflectionFunction(__NAMESPACE__ . '\\global_function')],
        ];
    }

    public function instanceMethod(): int
    {
        return global_function();
    }

    /**
     * @testdox checks an attempt to create a new autowiring context from different callable types.
     *
     * @dataProvider reflectionDataProvider
     */
    public function testCreation(\ReflectionFunctionAbstract $fn): void
    {
        $this->expectNotToPerformAssertions();

        new Autowired($fn);
    }

    /**
     * @testdox verifies that methods with no arguments return an empty array.
     *
     * @dataProvider reflectionDataProvider
     */
    public function testResolvingMethodWithoutArgumentsInvocation(\ReflectionFunctionAbstract $fn): void
    {
        $handler = new Autowired($fn);

        for ($i = 0; $i < 2; ++$i) {
            $this->assertSame([], $handler->resolve(range(0, $i)));
        }
    }

    /**
     * @testdox checks a method call with an object context (if allowed) or exception otherwise (if static ctx required)
     *
     * @dataProvider reflectionDataProvider
     */
    public function testInstanceCallMethodInvocation(\ReflectionFunctionAbstract $fn): void
    {
        $handler = new Autowired($fn);

        // If the static context is required, then the method invocation with
        // "this" context should return an BadMethodCallException error.
        if ($handler->isStaticContextRequired()) {
            $this->expectException(\BadMethodCallException::class);
        }

        $this->assertSame(0xDEAD_BEEF, $handler->call($this, []));
    }

    /**
     * @testdox checks a method call without an object context (if allowed) or exception otherwise (if ctx required)
     *
     * @dataProvider reflectionDataProvider
     */
    public function testStaticCallMethodInvocation(\ReflectionFunctionAbstract $fn): void
    {
        $handler = new Autowired($fn);

        // If the object context is required, then the method invocation without
        // "this" context should return an BadMethodCallException error.
        if ($handler->isObjectContextRequired()) {
            $this->expectException(\BadMethodCallException::class);
        }

        $this->assertSame(0xDEAD_BEEF, $handler->call(null, []));
    }
}
