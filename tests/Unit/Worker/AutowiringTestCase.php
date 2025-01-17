<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\JsonConverter;
use Temporal\Internal\Declaration\Dispatcher\AutowiredPayloads;

function global_function(): int
{
    return 0xDEAD_BEEF;
}

/**
 * @group unit
 * @group worker
 */
class AutowiringTestCase extends AbstractWorker
{
    public static function staticMethod(): int
    {
        return global_function();
    }

    public static function reflectionDataProvider(): array
    {
        $instance = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();

        return [
            // Instance Method
            static::class . '->instanceMethod' => [new \ReflectionMethod($instance, 'instanceMethod')],

            // Static Method
            static::class . '::staticMethod' => [new \ReflectionMethod(static::class . '::staticMethod')],

            // Function
            __NAMESPACE__ . '\\global_function' => [new \ReflectionFunction(__NAMESPACE__ . '\\global_function')],
        ];
    }

    public static function instanceMethod(): int
    {
        return global_function();
    }

    #[DataProvider('reflectionDataProvider')]
    #[TestDox("Checks an attempt to create a new autowiring context from different callable types")]
    public function testCreation(\ReflectionFunctionAbstract $fn): void
    {
        $this->expectNotToPerformAssertions();

        new AutowiredPayloads($fn, new DataConverter());
    }

    #[TestDox("Checks invocation with an object context or exception otherwise (if static context required)")]
    #[DataProvider('reflectionDataProvider')]
    public function testInstanceCallMethodInvocation(\ReflectionFunctionAbstract $fn): void
    {
        $handler = new AutowiredPayloads($fn, new DataConverter(new JsonConverter()));

        // If the static context is required, then the method invocation with
        // "this" context should return an BadMethodCallException error.
        if ($handler->isStaticContextRequired()) {
            $this->expectException(\BadMethodCallException::class);
        }

        $this->assertSame(0xDEAD_BEEF, $handler->dispatch($this, []));
    }
}
