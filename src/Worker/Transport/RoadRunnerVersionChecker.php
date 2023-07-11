<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

use Composer\Script\Event;
use Psr\Log\LoggerInterface;
use RoadRunner\VersionChecker\Exception\RoadrunnerNotInstalledException;
use RoadRunner\VersionChecker\Exception\UnsupportedVersionException;
use RoadRunner\VersionChecker\VersionChecker;
use Spiral\RoadRunner\Logger;

final class RoadRunnerVersionChecker
{
    private VersionChecker $checker;
    private LoggerInterface $logger;

    public function __construct(
        VersionChecker $checker = null,
        LoggerInterface $logger = null
    ) {
        $this->checker = $checker ?? new VersionChecker();
        $this->logger = $logger ?? new Logger();
    }

    public function check(): void
    {
        try {
            $this->checker->greaterThan();
        } catch (UnsupportedVersionException|RoadrunnerNotInstalledException $e) {
            $this->logger->warning($e->getMessage());
        }
    }

    public static function postUpdate(Event $event): void
    {
        $checker = new VersionChecker();

        try {
            $checker->greaterThan();
        } catch (UnsupportedVersionException|RoadrunnerNotInstalledException $e) {
            $event->getIO()->warning($e->getMessage());
        }
    }
}
