<?php

namespace Temporal\Tests\Unit\Interceptor;

use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * @group unit
 * @group interceptor
 */
class PipelineTestCase extends TestCase
{
    /**
     * Check that all middleware are executed in correct order.
     */
    public function testSimplePipelineOrder(): void
    {
        $pipeline = Pipeline::prepare([
            fn (string $s, callable $next) => $next($s . 'a') . 'w',
            fn (string $s, callable $next) => $next($s . 'b') . 'x',
            fn (string $s, callable $next) => $next($s . 'c') . 'y',
            fn (string $s, callable $next) => $next($s . 'd') . 'z',
        ]);

        self::assertSame('-abcdzyxw', $pipeline->with(fn(string $i) => $i, '__invoke')('-'));
    }

    public function testPipelineMultipleArgs(): void
    {
        $middleware = static function (int $i, stdClass $dto, DateTimeInterface $date, callable $next): mixed {
            ++$i;
            return $next($i, $dto, $date);
        };

        $pipeline = Pipeline::prepare([
            $middleware,
            $middleware,
            $middleware,
            $middleware,
        ]);

        $int = $pipeline->with(
            fn(int $i, stdClass $class, DateTimeInterface $date) => $i,
            '__invoke',
        )(
            1,
            new stdClass(),
            new \DateTimeImmutable(),
        );

        self::assertSame(5, $int);
    }
}
