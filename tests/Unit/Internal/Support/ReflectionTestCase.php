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
        yield [
            ['foo' => 1, 'bar' => 2, 'baz' => 3],
            [1, 2, 3],
        ];
        yield [
            ['foo' => 1, 'bar' => 2],
            [1, 2, 42],
        ];
        yield [
            ['foo' => 1, 'bar' => null],
            [1, null, 42],
        ];
        yield [
            ['foo' => 1, 'bar' => 2, 'baz' => 3, 'qux' => 4],
            [1, 2, 3],
        ];
        // Custom named order
        yield [
            ['bar' => 2, 'foo' => 1, 'baz' => 3],
            [1, 2, 3],
        ];
        yield [
            ['bar' => 2, 'foo' => 1],
            [1, 2, 42],
        ];
        // Numeric order
        yield [
            [1, 2, 3],
            [1, 2, 3],
        ];
        // Mixed order
        yield [
            [1, 'bar' => 2, 'baz' => 3],
            [1, 2, 3],
        ];
        yield [
            [1, null, 'baz' => 3],
            [1, null, 3],
        ];
        yield [
            [1, 'baz' => 3, 'bar' => null],
            [1, null, 3],
        ];
    }

    #[DataProvider('provideOrderArguments')]
    public function testOrderArguments(array $arguments, array $result): void
    {
        $reflection = new \ReflectionFunction($fn = static fn (int $foo, ?int $bar, int $baz = 42) => \func_get_args());

        $args = Reflection::orderArguments($reflection, $arguments);

        $this->assertIsList($args);
        $this->assertSame($result, $fn(...$args));
    }
}
