<?php

declare(strict_types=1);

namespace Unit\Worker\Transport;

use RoadRunner\VersionChecker\Version\ComparatorInterface;
use RoadRunner\VersionChecker\VersionChecker;
use Temporal\Tests\Unit\UnitTestCase;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RoadRunnerVersionChecker;

/**
 * @group unit
 */
final class RoadRunnerTestCase extends UnitTestCase
{
    public function testCreateShouldCallVersionCheck(): void
    {
        $comparator = $this->createMock(ComparatorInterface::class);
        $comparator
            ->expects($this->once())
            ->method('greaterThan')
            ->with('2023.1.0.0-dev', '2023.1.0')
            ->willReturn(true);

        $checker = new RoadRunnerVersionChecker(checker: new VersionChecker(comparator: $comparator));

        RoadRunner::create(versionChecker: $checker);

        \ob_get_clean();
    }
}
