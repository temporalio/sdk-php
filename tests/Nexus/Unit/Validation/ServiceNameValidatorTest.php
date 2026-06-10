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

    public function testDelegatesToPrintableAsciiValidator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Service Name.+printable non-whitespace ASCII/');
        ServiceNameValidator::assert("bad\nname");
    }
}
