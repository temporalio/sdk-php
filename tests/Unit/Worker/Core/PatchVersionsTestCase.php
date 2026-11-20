<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function provideUnsupportedVersions(): iterable
    {
        yield 'removed version' => ['change-1', 'Workflow code removed support of version 1 for "change" changeID. The oldest supported version is 2'];
        yield 'too new version' => ['change-4', 'Workflow code is too old to support version 4 for "change" changeID. The maximum supported version is 3'];
    }

    #[DataProvider('provideUnsupportedVersions')]
    public function testVersionOutsideSupportedRangeFails(string $notified, string $message): void
    {
        $patches = new PatchVersions();
        $patches->notify($notified);
        $commands = [];

        $this->expectExceptionObject(new \LogicException($message));
        $patches->version('change', 2, 3, true, $commands);
    }
}
