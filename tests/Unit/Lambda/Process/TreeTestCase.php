<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Lambda\Process\Tree;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(Tree::class)]
final class TreeTestCase extends AbstractUnit
{
    public function testParentIsReadFromTheFieldAfterTheCommand(): void
    {
        self::assertSame(4711, Tree::parentPid('1234 (rr) S 4711 1234 1234 0 -1 4194304'));
    }

    public function testCommandContainingSpacesAndParenthesesDoesNotShiftTheFields(): void
    {
        self::assertSame(
            4711,
            Tree::parentPid('1234 (php (worker) 1) S 4711 1234 1234 0 -1 4194304'),
        );
    }

    public function testStatWithoutAClosingParenthesisIsIgnored(): void
    {
        self::assertNull(Tree::parentPid('1234 rr S 4711'));
    }

    public function testTruncatedStatIsIgnored(): void
    {
        self::assertNull(Tree::parentPid('1234 (rr) S'));
    }
}
