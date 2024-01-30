<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Support\Reflection;

#[CoversClass(\Temporal\Internal\Support\Reflection::class)]
final class ReflectionTestCase extends TestCase
{

    public static function provideOrderArguments(): iterable
    {
        // Normal named order
        yield 'Normal order with three items' => [
            ['foo' => 1, 'bar' => 2, 'baz' => 3],
            [1, 2, 3],
        ];
        yield 'Normal order missing third item' => [
            ['foo' => 1, 'bar' => 2],
            [1, 2, 42],
        ];
        yield 'Normal order with null second item' => [
            ['foo' => 1, 'bar' => null],
            [1, null, 42],
        ];

        // Custom named order
        yield 'Custom order, keys reordered' => [
            ['bar' => 2, 'foo' => 1, 'baz' => 3],
            [1, 2, 3],
        ];
        yield 'Custom order with two items, default used for third' => [
            ['bar' => 2, 'foo' => 1],
            [1, 2, 42],
        ];

        // Numeric order
        yield 'Numeric order, simple list' => [
            [1, 2, 3],
            [1, 2, 3],
        ];

        // Mixed order
        yield 'Mixed order, numeric and named, all present' => [
            [1, 'bar' => 2, 'baz' => 3],
            [1, 2, 3],
        ];
        yield 'Mixed order with null second item' => [
            [1, null, 'baz' => 3],
            [1, null, 3],
        ];
        yield 'Mixed order, null in named key' => [
            [1, 'baz' => 3, 'bar' => null],
            [1, null, 3],
        ];
    }


    /**
     * @param array<int|string,?int> $arguments
     * @param list<?int> $expectedResult
     * @return void
     * @throws \ReflectionException
     */
    #[DataProvider('provideOrderArguments')]
    public function testOrderArguments(array $arguments, array $expectedResult): void
    {
        /**
         * @param int $foo
         * @param int|null $bar
         * @param int $baz
         *
         * @return array<?int>
         */
        $fn = static fn (int $foo, ?int $bar, int $baz = 42): array => \func_get_args();
        $reflection = new \ReflectionFunction($fn);

        $sortedArguments = Reflection::orderArguments($reflection, $arguments);

        $this->assertIsList($sortedArguments);
        $this->assertSame(
            $expectedResult,
            $fn(...$sortedArguments)
        );
    }

    public function testTooManyArguments(): void
    {
        $this->expectException(\Temporal\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            regularExpression: '/Too many arguments passed to .* expected 2, got 3./'
        );

        $reflection = new \ReflectionFunction(
            static fn (int $foo, int $bar): array => \func_get_args()
        );

        Reflection::orderArguments($reflection, [1, 2, 3]);
    }
}
