<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Lambda\ProcessTree;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(ProcessTree::class)]
final class ProcessTreeTestCase extends AbstractUnit
{
    public function testParentIsReadFromTheFieldAfterTheCommand(): void
    {
        self::assertSame(4711, ProcessTree::parentPid('1234 (rr) S 4711 1234 1234 0 -1 4194304'));
    }

    public function testCommandContainingSpacesAndParenthesesDoesNotShiftTheFields(): void
    {
        self::assertSame(
            4711,
            ProcessTree::parentPid('1234 (php (worker) 1) S 4711 1234 1234 0 -1 4194304'),
        );
    }

    public function testStatWithoutAClosingParenthesisIsIgnored(): void
    {
        self::assertNull(ProcessTree::parentPid('1234 rr S 4711'));
    }

    public function testTruncatedStatIsIgnored(): void
    {
        self::assertNull(ProcessTree::parentPid('1234 (rr) S'));
    }
}
