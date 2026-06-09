<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\WorkerFactory;

#[Service]
interface GuardSyncOnlyService
{
    #[Operation]
    public function greet(string $name): string;
}

class GuardSyncOnlyServiceImpl implements GuardSyncOnlyService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

#[Service]
interface GuardAsyncService
{
    #[AsyncOperation(output: 'string')]
    public function runAsync(string $input): OperationInfo;
}

class GuardAsyncServiceImpl implements GuardAsyncService
{
    public function runAsync(string $input): OperationInfo
    {
        return new OperationInfo('token', OperationState::Running);
    }
}

/**
 * @group unit
 * @group worker
 * @group nexus
 */
final class NexusRegistrationGuardTestCase extends AbstractUnit
{
    public function testSyncOnlyServiceWithoutClientIsAllowed(): void
    {
        $worker = WorkerFactory::create()->newWorker();

        $worker->registerNexusServiceImplementation(new GuardSyncOnlyServiceImpl());

        self::assertCount(1, $worker->getNexusServices());
    }

    public function testAsyncServiceWithoutClientThrows(): void
    {
        $worker = WorkerFactory::create()->newWorker();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('declares async operation "runAsync", which needs cluster access');

        $worker->registerNexusServiceImplementation(new GuardAsyncServiceImpl());
    }
}
