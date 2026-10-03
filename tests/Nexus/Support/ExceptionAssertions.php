<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Support;

use PHPUnit\Framework\Assert;

/**
 * Captures an exception thrown by the given callable and returns it for
 * further assertions. Use in tests that need to interrogate fields on the
 * thrown exception; for pure type/message checks prefer `expectException*`.
 *
 * Supersedes the `try { ...; self::fail(); } catch (...) { ... }` pattern.
 */
trait ExceptionAssertions
{
    /**
     * @template T of \Throwable
     * @param class-string<T> $expected
     * @return T
     */
    protected static function assertThrown(string $expected, callable $action): \Throwable
    {
        try {
            $action();
        } catch (\Throwable $e) {
            Assert::assertInstanceOf($expected, $e);
            return $e;
        }

        Assert::fail("Expected {$expected} but nothing was thrown");
    }
}
