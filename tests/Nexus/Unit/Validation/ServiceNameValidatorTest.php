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
use Temporal\Nexus\Validation\ServiceNameValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceNameValidator::class)]
#[UsesClass(PrintableAsciiValidator::class)]
final class ServiceNameValidatorTest extends TestCase
{
    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service Name must not be empty');
        ServiceNameValidator::assert('');
    }

    public function testAcceptsPrintableAscii(): void
    {
        ServiceNameValidator::assert('GreetingService');
        ServiceNameValidator::assert('com.example/Greeting');
        ServiceNameValidator::assert('a');

        self::assertTrue(true, 'ServiceNameValidator::assert() must not throw on valid printable ASCII.');
    }

    #[DataProvider('invalidNameProvider')]
    public function testRejectsInvalidBytes(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/printable non-whitespace ASCII/');
        ServiceNameValidator::assert($name);
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidNameProvider(): iterable
    {
        yield 'newline'         => ["bad\nname"];
        yield 'carriage return' => ["bad\rname"];
        yield 'null byte'       => ["bad\0name"];
        yield 'tab'              => ["bad\tname"];
        yield 'space'            => ["bad name"];
        yield 'utf-8 multibyte'  => ["имя"];
        yield 'del byte'         => ["bad\x7fname"];
    }
}
