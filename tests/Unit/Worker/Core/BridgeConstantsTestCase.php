<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\TestCase;
use Temporal\Internal\Bridge\Bridge;

final class BridgeConstantsTestCase extends TestCase
{
    public function testPhpConstantsMatchTheBridgeHeader(): void
    {
        $header = (string) \file_get_contents(\dirname(__DIR__, 4) . '/core/bridge/include/temporal_php_bridge.h');
        \preg_match_all('/^#define (\w+) (-?\d+)$/m', $header, $matches);
        $defines = \array_map(\intval(...), \array_combine($matches[1], $matches[2]));

        $constants = \array_filter(
            (new \ReflectionClass(Bridge::class))->getConstants(),
            static fn(string $name): bool => \preg_match('/^(KIND|STATUS|CALL)_/', $name) === 1,
            \ARRAY_FILTER_USE_KEY,
        );

        \ksort($defines);
        \ksort($constants);
        self::assertNotEmpty($defines);
        self::assertSame($defines, $constants);
    }
}
