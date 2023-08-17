<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Transport;

use Psr\Log\LoggerInterface;
use RoadRunner\VersionChecker\Exception\RoadrunnerNotInstalledException;
use RoadRunner\VersionChecker\Version\InstalledInterface;
use RoadRunner\VersionChecker\Version\RequiredInterface;
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('warning');

        $checker = new RoadRunnerVersionChecker(
            checker: new VersionChecker(installedVersion: $installed, requiredVersion: $required),
            logger: $logger
        );
        $checker->check();
    }

    public function testRoadRunnerIsNotInstalled(): void
    {
        $installed = $this->createMock(InstalledInterface::class);
        $installed
            ->expects($this->once())
            ->method('getInstalledVersion')
            ->willThrowException(new RoadrunnerNotInstalledException('Roadrunner is not installed.'));

        $required = $this->createMock(RequiredInterface::class);
        $required
            ->expects($this->once())
            ->method('getRequiredVersion')
            ->willReturn('2023.1.0');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Roadrunner is not installed.');

        $checker = new RoadRunnerVersionChecker(
            checker: new VersionChecker(installedVersion: $installed, requiredVersion: $required),
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

        $required = $this->createMock(RequiredInterface::class);
        $required
            ->expects($this->once())
            ->method('getRequiredVersion')
            ->willReturn('2023.1.0');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Installed RoadRunner version `2.12.2` not supported. Requires version `2023.1.0` or higher.');

        $checker = new RoadRunnerVersionChecker(
            checker: new VersionChecker(installedVersion: $installed, requiredVersion: $required),
            logger: $logger
        );
        $checker->check();
    }
}
