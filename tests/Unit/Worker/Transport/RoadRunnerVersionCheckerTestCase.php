<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Transport;

use Psr\Log\LoggerInterface;
use RoadRunner\VersionChecker\Exception\RoadrunnerNotInstalledException;
use RoadRunner\VersionChecker\Version\InstalledInterface;
use RoadRunner\VersionChecker\VersionChecker;
use Temporal\Tests\Unit\UnitTestCase;
use Temporal\Worker\Transport\RoadRunnerVersionChecker;

/**
 * @group unit
 */
final class RoadRunnerVersionCheckerTestCase extends UnitTestCase
{
    public function testCheckSuccess(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('warning');

        $checker = new RoadRunnerVersionChecker(logger: $logger);
        $checker->check();
    }

    public function testRoadRunnerIsNotInstalled(): void
    {
        $installed = $this->createMock(InstalledInterface::class);
        $installed
            ->expects($this->once())
            ->method('getInstalledVersion')
            ->willThrowException(new RoadrunnerNotInstalledException('Roadrunner is not installed.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Roadrunner is not installed.');

        $checker = new RoadRunnerVersionChecker(
            checker: new VersionChecker(installedVersion: $installed),
            logger: $logger
        );
        $checker->check();
    }

    public function testCheckFail(): void
    {
        $installed = $this->createMock(InstalledInterface::class);
        $installed
            ->expects($this->once())
            ->method('getInstalledVersion')
            ->willReturn('2.12.2');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Installed RoadRunner version `2.12.2` not supported. Requires version `2023.1.0` or higher.');

        $checker = new RoadRunnerVersionChecker(
            checker: new VersionChecker(installedVersion: $installed),
            logger: $logger
        );
        $checker->check();
    }
}
