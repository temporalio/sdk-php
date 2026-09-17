<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\OperationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationState::class)]
final class OperationStateTest extends TestCase
{
    public function testAllStatesUseSpecLowercaseValues(): void
    {
        // Values must match https://github.com/nexus-rpc/api/blob/main/SPEC.md
        // which requires lowercase strings on the wire.
        self::assertSame('running', OperationState::Running->value);
        self::assertSame('succeeded', OperationState::Succeeded->value);
        self::assertSame('failed', OperationState::Failed->value);
        self::assertSame('canceled', OperationState::Canceled->value);
    }

    public function testFromString(): void
    {
        self::assertSame(OperationState::Running, OperationState::from('running'));
        self::assertSame(OperationState::Failed, OperationState::from('failed'));
    }
}
