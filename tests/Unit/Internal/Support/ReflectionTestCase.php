<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Exception\InvalidArgumentException;
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
        yield 'Numeric order, extra arguments' => [
            [1, 2, 3, 7, 42, 10],
            [1, 2, 3, 7, 42, 10],
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
     * @param array<int|string, int|null> $arguments
     * @param list<int|null> $expectedResult
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

    public function testOrderArgumentsSpreadFunction(): void
    {
        $fn = static fn (int $foo, int ...$rest): array => \func_get_args();
        $reflection = new \ReflectionFunction($fn);

        $sortedArguments = Reflection::orderArguments(
            $reflection,
            [1, 2, 3, 4]
        );

        $this->assertSame(
            [1, 2, 3, 4],
            $fn(...$sortedArguments)
        );
    }

    public function testOrderArgumentsConflictOrder(): void
    {
        $fn = static fn (int $foo, int $bar, int $baz = 42): array => \func_get_args();
        $reflection = new \ReflectionFunction($fn);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            regularExpression: '/Parameter .* \$foo .* received two conflicting arguments - named and positional/'
        );


        Reflection::orderArguments(
            $reflection,
            [1, 2, 'foo' => 42],
        );
    }

    public function testOrderArgumentsConflictOrderOnMethod(): void
    {
        $reflection = new \ReflectionMethod($this, 'publicTestFunction');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Parameter #%d $%s of %s received two conflicting arguments - named and positional.',
            0,
            'foo',
            __CLASS__ . '::publicTestFunction()',
        ));

        Reflection::orderArguments(
            $reflection,
            [1, 'foo' => 42],
        );
    }

    public function testOrderArgumentsExtraNamedArgumentsOnMethod(): void
    {
        $reflection = new \ReflectionMethod($this, __FUNCTION__);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Too many arguments passed to %s: defined %d, got %d.',
            __CLASS__ . '::' . __FUNCTION__ . '()',
            $reflection->getNumberOfParameters(),
            2,
        ));

        Reflection::orderArguments(
            $reflection,
            ['foo' => 42, 'bar' => 13],
        );
    }

    public function publicTestFunction(int $foo, int $bar): void
    {
    }
}
