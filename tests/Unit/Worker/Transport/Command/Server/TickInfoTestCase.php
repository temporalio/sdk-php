<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Transport\Command\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow\WorkflowInfo;

#[CoversClass(TickInfo::class)]
final class TickInfoTestCase extends AbstractUnit
{
    public function testApplyToCopiesHistoryAndContinueAsNew(): void
    {
        $info = new WorkflowInfo();
        $tick = new TickInfo(
            time: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            historyLength: 42,
            historySize: 1024,
            continueAsNewSuggested: true,
        );

        $tick->applyTo($info);

        self::assertSame(42, $info->historyLength);
        self::assertSame(1024, $info->historySize);
        self::assertTrue($info->shouldContinueAsNew);
    }

    public function testApplyToOverwritesPreviousValues(): void
    {
        $info = new WorkflowInfo();
        $info->historyLength = 99;
        $info->historySize = 99;
        $info->shouldContinueAsNew = true;

        (new TickInfo(time: new \DateTimeImmutable('2026-01-01T00:00:00+00:00')))->applyTo($info);

        self::assertSame(0, $info->historyLength);
        self::assertSame(0, $info->historySize);
        self::assertFalse($info->shouldContinueAsNew);
    }
}
