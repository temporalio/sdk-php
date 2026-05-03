<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Validation;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use Temporal\Nexus\Validation\OperationTokenValidator;
use Temporal\Nexus\Validation\PrintableAsciiValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationTokenValidator::class)]
#[UsesClass(PrintableAsciiValidator::class)]
final class OperationTokenValidatorTest extends TestCase
{
    use ExceptionAssertions;

    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation Token must not be empty');
        OperationTokenValidator::assert('');
    }

    public function testAcceptsPrintableAscii(): void
    {
        OperationTokenValidator::assert('job-abc123-XYZ_~.!');

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsFullPrintableRange(): void
    {
        $token = '';
        for ($c = 0x21; $c <= 0x7E; $c++) {
            $token .= \chr($c);
        }

        OperationTokenValidator::assert($token);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidTokenProvider')]
    public function testRejectsInvalidBytes(string $token): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/printable non-whitespace ASCII/');
        OperationTokenValidator::assert($token);
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidTokenProvider(): iterable
    {
        yield 'newline'           => ["bad\ntoken"];
        yield 'carriage return'   => ["bad\rtoken"];
        yield 'null byte'         => ["bad\0token"];
        yield 'tab'               => ["bad\ttoken"];
        yield 'space'             => ["bad token"];
        yield 'utf-8 multibyte'   => ["токен"];
        yield 'high-bit byte'     => ["\xff\xfe"];
        yield 'del byte'          => ["bad\x7ftoken"];
        yield 'control char 0x01' => ["bad\x01token"];
    }

    public function testErrorMessageIncludesBadOffset(): void
    {
        $e = self::assertThrown(
            InvalidArgumentException::class,
            static fn() => OperationTokenValidator::assert("ok\nbad"),
        );

        self::assertStringContainsString('offset 2', $e->getMessage());
        self::assertStringContainsString('6 bytes', $e->getMessage());
    }
}
