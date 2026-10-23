<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\WorkerFactory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Worker\Transport\CommandBatch;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\WorkerFactory;

#[CoversClass(WorkerFactory::class)]
final class RoadRunnerDispatchTestCase extends TestCase
{
    private const TASK_QUEUE = 'dispatch-queue';

    public function testBatchesReachTheRouterTheClientAndTheTaskQueueWorker(): void
    {
        $factory = WorkerFactory::create(rpc: $this->createMock(RPCConnectionInterface::class));
        $factory->newWorker(self::TASK_QUEUE);
        $host = $this->createMock(HostConnectionInterface::class);
        $host->method('waitBatch')->willReturnOnConsecutiveCalls(
            new CommandBatch('[{"command":"GetWorkerInfo"},{"id":7,"payloads":""}]', []),
            new CommandBatch('[{"command":"GetWorkerInfo"}]', ['taskQueue' => self::TASK_QUEUE]),
            new CommandBatch('[{"command":"GetWorkerInfo"}]', ['taskQueue' => 'missing']),
            null,
        );
        $frames = [];
        $host->method('send')->willReturnCallback(static function (string $frame) use (&$frames): void {
            $frames[] = \json_decode($frame, true, flags: \JSON_THROW_ON_ERROR);
        });
        $host->expects($this->never())->method('error');

        $this->assertSame(0, $factory->run($host));

        $this->assertCount(3, $frames);
        $this->assertStringContainsString(self::TASK_QUEUE, \base64_decode($frames[0][0]['payloads']));
        $this->assertSame('Got the response to undefined request 7', $frames[0][1]['options']['message']);
        $this->assertSame('Method "GetWorkerInfo" is not registered', self::failureMessage($frames[1][0]));
        $this->assertSame('Cannot find a worker for task queue "missing"', self::failureMessage($frames[2][0]));
    }

    private static function failureMessage(array $response): string
    {
        $failure = new Failure();
        $failure->mergeFromString(\base64_decode($response['failure']));

        return $failure->getMessage();
    }
}
