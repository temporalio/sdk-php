<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Nexus\Validation\OperationTokenValidator;
use Temporal\Nexus\Validation\PrintableAsciiValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationInfo::class)]
#[UsesClass(OperationTokenValidator::class)]
#[UsesClass(PrintableAsciiValidator::class)]
#[UsesClass(InvalidArgumentException::class)]
final class OperationInfoTest extends TestCase
{
    public function testConstructsWithTokenAndState(): void
    {
        $info = new OperationInfo('tok-1', OperationState::Running);

        self::assertSame('tok-1', $info->token);
        self::assertSame(OperationState::Running, $info->state);
    }

    public function testCarriesEachState(): void
    {
        foreach (OperationState::cases() as $state) {
            $info = new OperationInfo('t', $state);
            self::assertSame($state, $info->state);
        }
    }

    public function testRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation Token must not be empty');
        new OperationInfo('', OperationState::Succeeded);
    }

    public function testRejectsInvalidTokenBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/printable non-whitespace ASCII/');
        new OperationInfo("bad\ntoken", OperationState::Failed);
    }
}
