<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Bridge;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Bridge\CoreEnvironment;

final class CoreEnvironmentTestCase extends TestCase
{
    private const NAME = 'TEMPORAL_CORE_TEST_VALUE';

    public static function provideValues(): iterable
    {
        yield 'string unset' => [null, static fn(): ?string => CoreEnvironment::string(self::NAME), null];
        yield 'string empty' => ['', static fn(): ?string => CoreEnvironment::string(self::NAME), null];
        yield 'string set' => ['value', static fn(): ?string => CoreEnvironment::string(self::NAME), 'value'];
        yield 'integer default' => [null, static fn(): int => CoreEnvironment::integer(self::NAME, 7, 1), 7];
        yield 'integer set' => ['3', static fn(): int => CoreEnvironment::integer(self::NAME, 7, 1), 3];
        yield 'integer below minimum' => ['0', static fn(): int => CoreEnvironment::integer(self::NAME, 7, 1), new \InvalidArgumentException(self::NAME . ' must be an integer not less than 1, "0" given')];
        yield 'integer not a number' => ['many', static fn(): int => CoreEnvironment::integer(self::NAME, 7, 1), new \InvalidArgumentException(self::NAME . ' must be an integer not less than 1, "many" given')];
        yield 'flag default' => [null, static fn(): bool => CoreEnvironment::flag(self::NAME, true), true];
        yield 'flag off' => ['off', static fn(): bool => CoreEnvironment::flag(self::NAME, true), false];
        yield 'flag on' => ['1', static fn(): bool => CoreEnvironment::flag(self::NAME, false), true];
        yield 'fraction unset' => [null, static fn(): ?float => CoreEnvironment::fraction(self::NAME), null];
        yield 'fraction set' => ['0.75', static fn(): ?float => CoreEnvironment::fraction(self::NAME), 0.75];
        yield 'fraction above one' => ['1.5', static fn(): ?float => CoreEnvironment::fraction(self::NAME), new \InvalidArgumentException(self::NAME . ' must be a number from 0 to 1, "1.5" given')];
        yield 'fraction not a number' => ['most', static fn(): ?float => CoreEnvironment::fraction(self::NAME), new \InvalidArgumentException(self::NAME . ' must be a number from 0 to 1, "most" given')];
        yield 'map unset' => [null, static fn(): ?array => CoreEnvironment::map(self::NAME), null];
        yield 'map set' => [' a = b ,token=x=y', static fn(): ?array => CoreEnvironment::map(self::NAME), ['a' => 'b', 'token' => 'x=y']];
        yield 'map pair without value' => ['a=b,c', static fn(): ?array => CoreEnvironment::map(self::NAME), new \InvalidArgumentException(self::NAME . ' must be a comma-separated list of key=value pairs, "a=b,c" given')];
        yield 'map pair without key' => ['=b', static fn(): ?array => CoreEnvironment::map(self::NAME), new \InvalidArgumentException(self::NAME . ' must be a comma-separated list of key=value pairs, "=b" given')];
        yield 'flag invalid' => ['maybe', static fn(): bool => CoreEnvironment::flag(self::NAME, true), new \InvalidArgumentException(self::NAME . ' must be a boolean, "maybe" given')];
    }

    #[DataProvider('provideValues')]
    public function testRead(?string $value, \Closure $read, mixed $expected): void
    {
        $_SERVER[self::NAME] = $value;
        try {
            if ($expected instanceof \Throwable) {
                $this->expectExceptionObject($expected);
            }
            self::assertSame($expected, $read());
        } finally {
            unset($_SERVER[self::NAME]);
        }
    }
}
