<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Validation;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Validation\PrintableAsciiValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintableAsciiValidator::class)]
final class PrintableAsciiValidatorTest extends TestCase
{
    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Thing must not be empty');
        PrintableAsciiValidator::assert('', 'Thing');
    }

    public function testAcceptsPrintableAscii(): void
    {
        PrintableAsciiValidator::assert('hello-world.42_%', 'Thing');
        PrintableAsciiValidator::assert('!~', 'Thing');
        self::assertTrue(true);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function badCharProvider(): iterable
    {
        yield 'leading space'   => [' a', 0];
        yield 'embedded space'  => ['a b', 1];
        yield 'tab'             => ["a\tb", 1];
        yield 'newline'         => ["a\nb", 1];
        yield 'del'             => ["a\x7Fb", 1];
        yield 'high byte'       => ["a\xFFb", 1];
    }

    #[DataProvider('badCharProvider')]
    public function testRejectsNonPrintable(string $value, int $offset): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("first bad char at offset {$offset}");
        PrintableAsciiValidator::assert($value, 'Thing');
    }
}
