<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Transport\Router;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Transport\Router\CancelWorkflow;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * @internal
 */
#[CoversClass(CancelWorkflow::class)]
#[UsesClass(CanceledFailure::class)]
#[UsesClass(ServerRequest::class)]
final class CancelWorkflowTestCase extends TestCase
{
    #[Test]
    public function causeBecomesCancelReason(): void
    {
        $process = $this->createMock(Process::class);
        $process
            ->expects(self::once())
            ->method('cancel')
            ->with(self::callback(
                static fn(CanceledFailure $reason): bool => $reason->getMessage() === 'operator asked',
            ));

        $this->handle($process, ['runId' => 'run-1', 'cause' => 'operator asked']);
    }

    #[Test]
    public function emptyCauseLeavesNoCancelReason(): void
    {
        $process = $this->createMock(Process::class);
        $process->expects(self::once())->method('cancel')->with(null);

        $this->handle($process, ['runId' => 'run-1', 'cause' => '']);
    }

    #[Test]
    public function absentCauseLeavesNoCancelReason(): void
    {
        $process = $this->createMock(Process::class);
        $process->expects(self::once())->method('cancel')->with(null);

        $this->handle($process, ['runId' => 'run-1']);
    }

    private function handle(Process $process, array $options): void
    {
        $running = $this->createMock(RepositoryInterface::class);
        $running->method('find')->with('run-1')->willReturn($process);

        $resolver = new Deferred();
        (new CancelWorkflow($running))->handle(
            new ServerRequest('CancelWorkflow', new TickInfo(new \DateTimeImmutable()), $options),
            [],
            $resolver,
        );
    }
}
