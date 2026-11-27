<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Stability\Eviction;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Logger\LogRecord;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\HandlerUnfinishedPolicy;
use Temporal\Workflow\WorkflowMethod;

use function React\Promise\all;

class EvictionTest extends TestCase
{
    private const RELEASE_TIMEOUT_SECONDS = 10;

    #[Test]
    public function evictionRunsFinallyBlocksAndReleasesTheWorkflowWithoutTheGarbageCollector(
        #[Stub('Extra_Stability_Eviction')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        $stub->signal('wait');
        $stub->signal('finish');

        self::assertSame('done', $stub->getResult());

        $deadline = \time() + self::RELEASE_TIMEOUT_SECONDS;
        while (!$logger->hasMessage('/^released$/') && \time() < $deadline) {
            \usleep(100_000);
        }

        self::assertSame(
            [
                ['finally', ['scope' => 'signal', 'replaying' => true]],
                ['finally', ['scope' => 'async', 'replaying' => true]],
                ['released', ['gc_runs' => 0]],
            ],
            \array_map(
                static fn(LogRecord $record): array => [$record->message, $record->context],
                $logger->getRecords(),
            ),
        );
    }
}

final class Sentinel
{
    public static int $gcRuns = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __destruct()
    {
        $this->logger->info('released', ['gc_runs' => \gc_status()['runs'] - self::$gcRuns]);
    }
}

#[Workflow\WorkflowInterface]
class TestWorkflow
{
    private bool $finish = false;
    private LoggerInterface $logger;
    private Sentinel $sentinel;

    #[WorkflowMethod('Extra_Stability_Eviction')]
    public function handle(): \Generator
    {
        $this->logger = LoggerFactory::createServerLogger(Workflow::getInfo()->taskQueue);
        $this->sentinel = new Sentinel($this->logger);

        Workflow::async(fn(): \Generator => $this->guarded('async'));
        $held = Promise::all([Workflow::timer(3600), Workflow::timer(3600)]);
        $cancellable = new Deferred(fn() => $this->logger->info('canceller'));
        $fork = all([Workflow::timer(3600), $cancellable->promise()]);
        $fork->then(fn() => $this->logger->info('callback'));

        yield Workflow::await(fn(): bool => $this->finish);

        return 'done';
    }

    #[Workflow\SignalMethod('wait', unfinishedPolicy: HandlerUnfinishedPolicy::Abandon)]
    public function wait(): \Generator
    {
        yield from $this->guarded('signal');
    }

    #[Workflow\SignalMethod('finish')]
    public function finish(): void
    {
        $this->finish = true;
    }

    private function guarded(string $scope): \Generator
    {
        try {
            yield Workflow::timer(3600);
        } catch (\Throwable) {
            $this->logger->info('catch', ['scope' => $scope]);
        } finally {
            Sentinel::$gcRuns = \gc_status()['runs'];
            $this->logger->info('finally', ['scope' => $scope, 'replaying' => Workflow::isReplaying()]);
        }
    }
}
