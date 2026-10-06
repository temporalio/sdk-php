<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Testing\CoreWorkerFactory;
use Temporal\Testing\WorkerMock;

#[CoversClass(CoreWorkerFactory::class)]
final class CoreWorkerFactoryTestCase extends TestCase
{
    public function testNewWorkerIsAMockThatKeepsTheTaskQueue(): void
    {
        $worker = CoreWorkerFactory::create(logger: new NullLogger())->newWorker('mocked-queue');

        $this->assertInstanceOf(WorkerMock::class, $worker);
        $this->assertSame('mocked-queue', $worker->getID());
    }
}
