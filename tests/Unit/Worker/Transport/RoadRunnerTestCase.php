<?php

declare(strict_types=1);

namespace Unit\Worker\Transport;

use RoadRunner\VersionChecker\Version\ComparatorInterface;
use RoadRunner\VersionChecker\Version\InstalledInterface;
use RoadRunner\VersionChecker\Version\RequiredInterface;
use RoadRunner\VersionChecker\VersionChecker;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RoadRunnerVersionChecker;

/**
 * @group unit
 */
final class RoadRunnerTestCase extends AbstractUnit
{
    public function testCreateShouldCallVersionCheck(): void
    {
        $installed = $this->createMock(InstalledInterface::class);
        $installed
            ->expects($this->once())
            ->method('getInstalledVersion')
            ->willReturn('2023.1.0');

        $required = $this->createMock(RequiredInterface::class);
        $required
            ->expects($this->once())
            ->method('getRequiredVersion')
            ->willReturn('2023.1.0');

        $comparator = $this->createMock(ComparatorInterface::class);
        $comparator
            ->expects($this->once())
            ->method('greaterThan')
            ->with('2023.1.0', '2023.1.0')
            ->willReturn(true);

        $checker = new RoadRunnerVersionChecker(checker: new VersionChecker(
            installedVersion: $installed,
            requiredVersion: $required,
            comparator: $comparator
        ));

        RoadRunner::create(versionChecker: $checker);

        \ob_get_clean();
    }
}
