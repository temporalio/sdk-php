<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Internal\Nexus\NexusEnvironment;
use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusEnvironment::class)]
final class NexusEnvironmentTestCase extends AbstractUnit
{
    public function testRejectsEmptyNamespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('namespace must not be empty');

        new NexusEnvironment('', 'tq', $this->createMock(WorkflowClientInterface::class));
    }

    public function testRejectsEmptyTaskQueue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('taskQueue must not be empty');

        new NexusEnvironment('ns', '', $this->createMock(WorkflowClientInterface::class));
    }
}
