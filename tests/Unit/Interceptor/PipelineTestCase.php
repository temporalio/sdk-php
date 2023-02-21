<?php

namespace Temporal\Tests\Unit\Interceptor;

use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Temporal\Interceptor\Pipeline;

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

        self::assertSame('-abcdzyxw', $pipeline->execute('__invoke', fn (string $i) => $i, '-'));
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

        $int = $pipeline->execute(
            '__invoke',
            fn(int $i, stdClass $class, DateTimeInterface $date) => $i,
            1,
            new stdClass(),
            new \DateTimeImmutable(),
        );

        self::assertSame(5, $int);
    }
}
