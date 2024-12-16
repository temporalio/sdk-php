<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Workflow\Process\DeferredGenerator;

/**
 * @psalm-type Action = 'current'|'send'|'key'|'next'|'valid'|'rewind'|'getReturn'
 */
final class DeferredGeneratorTestCase extends TestCase
{
    public function testSimple(): void
    {
        $this->compare(
            fn() => (function () {
                yield 1;
                yield 42 => 2;
                yield 3;
            })(),
            [
                'current', 'key', 'current', 'key',
                'next',
                'current', 'key', 'current', 'key', 'valid',
                'next',
                ['send', 'foo'],
                'current', 'key', 'current', 'key', 'valid',
            ],
        );
    }

    public function testSendingValues(): void
    {
        $this->compare(
            fn() => (function () {
                $a = yield;
                $b = yield $a;
                $c = yield $b;
                return [$a, $b, $c];
            })(),
            [
                ['send', 'foo'],
                ['send', 'bar'],
                ['send', 'baz'],
                'current', 'key', 'current', 'key', 'valid',
            ],
        );
    }

    public function testThrowingExceptions(): void
    {
        $this->compare(
            fn() => (function () {
                try {
                    yield;
                    throw new \Exception('foo');
                } catch (\Exception $e) {
                    yield $e->getMessage();
                }
            })(),
            [
                'current', 'key', 'current', 'key', 'valid',
                'next',
                'current', 'key', 'current', 'key', 'valid',
                'next',
                'rewind',
            ],
        );
    }

    public function testReturn(): void
    {
        $this->compare(
            fn() => (function () {
                yield 1;
                return 2;
            })(),
            [
                'current', 'key', 'current', 'key', 'valid',
                'next',
                'getReturn',
            ],
        );
    }

    public function testEmpty(): void
    {
        $this->compare(
            fn() => (function () {
                yield from [];
            })(),
            [
                'current', 'key', 'current', 'key', 'valid',
                'next',
                'rewind',
            ],
        );
    }

    public function testEmptyReturn(): void
    {
        $this->compare(
            fn() => (function () {
                return;
                yield;
            })(),
            [
                'current', 'key', 'current', 'key', 'valid',
                'next',
                'getReturn',
            ],
        );
    }

    public function testEmptyThrow(): void
    {
        $this->compare(
            fn() => (function () {
                throw new \Exception('foo');
                yield;
            })(),
            ['current', 'key', 'current', 'key', 'valid', 'getReturn', 'next', 'rewind'],
        );
    }

    public function testEmptyThrowValid(): void
    {
        $this->compare(
            fn() => (function () {
                throw new \Exception('foo');
                yield;
            })(),
            ['valid', 'valid'],
        );
    }

    public function testEmptyThrowGetReturn(): void
    {
        $this->compare(
            fn() => (function () {
                throw new \Exception('foo');
                yield;
            })(),
            ['getReturn', 'getReturn'],
        );
    }

    public function testEmptyThrowGetKey(): void
    {
        $this->compare(
            fn() => (function () {
                throw new \Exception('foo');
                yield;
            })(),
            ['key', 'key'],
        );
    }

    /**
     * @param callable(): \Generator $generatorFactory
     * @param iterable<Action|int, array{Action, mixed}> $actions
     * @return void
     */
    private function compare(
        callable $generatorFactory,
        iterable $actions,
    ): void {
        $c1 = $c2 = null;
        $caught = false;
        $gen = $generatorFactory();
        $def = DeferredGenerator::fromGenerator($generatorFactory());
        $def->catch(function (\Throwable $e) use (&$c1) {
            $c1 = $e;
        });
        $lazy = DeferredGenerator::fromHandler($generatorFactory, EncodedValues::empty());
        $lazy->catch(function (\Throwable $e) use (&$c2) {
            $c2 = $e;
        });


        $i = 0;
        foreach ($actions as $tuple) {
            ++$i;
            $argLess = \is_string($tuple);
            $method = $argLess ? $tuple : $tuple[0];
            $arg = $argLess ? null : $tuple[1];
            $c1 = $c2 = $e = $e2 = $e3 = $result = $result2 = $result3 = null;

            try {
                $result = $argLess ? $gen->$method() : $gen->$method($arg);
            } catch (\Throwable $e) {
                # ignore
            }

            try {
                $result2 = $argLess ? $def->$method() : $def->$method($arg);
            } catch (\Throwable $e2) {
                # ignore
            }

            try {
                $result3 = $argLess ? $lazy->$method() : $lazy->$method($arg);
            } catch (\Throwable $e3) {
                # ignore
            }

            $this->assertSame($result, $result2, "Generator and DeferredGenerator results differ [$i] `$method`");
            $this->assertSame($result, $result3, "Generator and DeferredGenerator results differ [$i] `$method`");
            if ($caught) {
                $this->assertNull($c1, "Error was caught twice [$i] `$method`");
                $this->assertNull($c2, "Error was caught twice [$i] `$method`");
            }
            if ($e !== null) {
                $this->assertNotNull($e2, "Generator and DeferredGenerator exceptions differ [$i] `$method`");
                $this->assertNotNull($e3, "Generator and DeferredGenerator exceptions differ [$i] `$method`");
                if (!$caught && !\in_array($method, ['rewind'], true)) {
                    $this->assertNotNull($c1, "Error was not caught [$i] `$method`");
                    $this->assertNotNull($c2, "Error was not caught [$i] `$method`");
                    $caught = true;
                }
            } else {
                $this->assertNull($e2, "Generator and DeferredGenerator exceptions differ [$i] `$method`");
                $this->assertNull($e3, "Generator and DeferredGenerator exceptions differ [$i] `$method`");
                $this->assertNull($c1, "There must be no error caught [$i] `$method`");
                $this->assertNull($c2, "There must be no error caught [$i] `$method`");
            }
        }
    }
}
