<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\TestCase;
use Temporal\Worker\Core\PatchVersions;

final class PatchVersionsTestCase extends TestCase
{
    public function testNotifiedVersionIsReplayedWithMarker(): void
    {
        $patches = new PatchVersions();
        $patches->notify('my-change-3');
        $commands = [];

        self::assertSame(3, $patches->version('my-change', 1, 5, true, $commands));
        self::assertSame('my-change-3', $commands[0]->getSetPatchMarker()->getPatchId());
    }

    public function testForeignPatchIdsAreIgnored(): void
    {
        $patches = new PatchVersions();
        $patches->notify('core-patch');
        $patches->notify('nodash');
        $commands = [];

        self::assertSame(-1, $patches->version('core', -1, 5, true, $commands));
        self::assertSame(-1, $patches->version('nodash', -1, 5, true, $commands));
        self::assertSame([], $commands);
    }

    public function testNewExecutionTakesMaxSupportedOnce(): void
    {
        $patches = new PatchVersions();
        $commands = [];

        self::assertSame(2, $patches->version('change', 1, 2, false, $commands));
        self::assertSame(2, $patches->version('change', 1, 2, false, $commands));
        self::assertCount(1, $commands);
    }

    public function testVersionOutsideSupportedRangeFails(): void
    {
        $patches = new PatchVersions();
        $patches->notify('change-1');
        $commands = [];

        $this->expectException(\LogicException::class);
        $patches->version('change', 2, 3, true, $commands);
    }
}
