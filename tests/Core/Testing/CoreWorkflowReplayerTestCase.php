<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Api\History\V1\History;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Client\Common\Paginator;
use Temporal\Client\Workflow\WorkflowExecutionHistory;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Testing\Replay\CoreWorkflowReplayer;
use Temporal\Testing\Replay\Exception\InvalidArgumentException;
use Temporal\Testing\Replay\Exception\NonDeterministicWorkflowException;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Testing\Replay\HistoryJsonCodec;
use Temporal\Tests\Workflow\SimpleWorkflow;
use Temporal\Tests\Workflow\WorkflowWithSequence;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Workflow\WorkflowExecution;

#[CoversClass(CoreWorkflowReplayer::class)]
final class CoreWorkflowReplayerTestCase extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/history/squence-workflow-damaged.json';
    private const WORKFLOW_TYPE = 'WorkflowWithSequence';
    private const LAST_DETERMINISTIC_EVENT_ID = 11;

    public function testHistoryUpToTheLastDeterministicEventReplays(): void
    {
        $this->expectNotToPerformAssertions();

        self::replayer()->replayFromJSON(self::WORKFLOW_TYPE, new \SplFileInfo(self::FIXTURE), self::LAST_DETERMINISTIC_EVENT_ID);
    }

    public function testFullDamagedHistoryIsNonDeterministic(): void
    {
        $this->expectException(NonDeterministicWorkflowException::class);

        self::replayer()->replayFromJSON(self::WORKFLOW_TYPE, self::FIXTURE);
    }

    public function testWorkflowTypeThatIsNotRegisteredFailsTheReplay(): void
    {
        $this->expectException(ReplayerException::class);
        $this->expectExceptionCode(13);

        self::replayer(workflow: SimpleWorkflow::class)->replayFromJSON(self::WORKFLOW_TYPE, self::FIXTURE);
    }

    public function testMissingFileIsAnInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        self::replayer()->replayFromJSON(self::WORKFLOW_TYPE, self::FIXTURE . '.missing');
    }

    public function testUnreadableFileIsAnInvalidArgument(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'history');
        \chmod($file, 0);
        \set_error_handler(static fn(): bool => true);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('cannot be read');

            self::replayer()->replayFromJSON(self::WORKFLOW_TYPE, $file);
        } finally {
            \restore_error_handler();
            \unlink($file);
        }
    }

    public function testHistoryObjectReplays(): void
    {
        $this->expectNotToPerformAssertions();

        self::replayer()->replayHistory(self::deterministicHistory());
    }

    public function testEmptyHistoryIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('History is empty or broken.');

        self::replayer()->replayHistory(new History());
    }

    public function testServerHistoryNeedsAClient(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A workflow client is required');

        self::replayer()->replayFromServer(self::WORKFLOW_TYPE, new WorkflowExecution('id', 'run'));
    }

    public function testDownloadNeedsAClient(): void
    {
        $this->expectException(\LogicException::class);

        self::replayer()->downloadHistory(self::WORKFLOW_TYPE, new WorkflowExecution('id', 'run'), \sys_get_temp_dir() . '/unused.json');
    }

    public function testServerHistoryReplays(): void
    {
        $execution = new WorkflowExecution('id', 'run');

        self::replayer(self::client($execution))->replayFromServer(self::WORKFLOW_TYPE, $execution);
    }

    public function testDownloadWritesTheHistoryAsJson(): void
    {
        $execution = new WorkflowExecution('id', 'run');
        $file = \tempnam(\sys_get_temp_dir(), 'history');
        try {
            self::replayer(self::client($execution))->downloadHistory(self::WORKFLOW_TYPE, $execution, $file);

            $this->assertSame(
                self::deterministicHistory()->serializeToString(),
                HistoryJsonCodec::decode((string) \file_get_contents($file))->serializeToString(),
            );
        } finally {
            \unlink($file);
        }
    }

    public function testDownloadToAnUnwritablePathFails(): void
    {
        $execution = new WorkflowExecution('id', 'run');
        \set_error_handler(static fn(): bool => true);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to write the history');

            self::replayer(self::client($execution))->downloadHistory(self::WORKFLOW_TYPE, $execution, self::FIXTURE . '.missing/history.json');
        } finally {
            \restore_error_handler();
        }
    }

    private static function replayer(?WorkflowClientInterface $client = null, string $workflow = WorkflowWithSequence::class): CoreWorkflowReplayer
    {
        $factory = CoreWorkerFactory::create(logger: new NullLogger());
        $factory->newWorker()->registerWorkflowTypes($workflow);

        return new CoreWorkflowReplayer($factory, $client);
    }

    private static function deterministicHistory(): History
    {
        return HistoryJsonCodec::decode((string) \file_get_contents(self::FIXTURE), self::LAST_DETERMINISTIC_EVENT_ID);
    }

    private function client(WorkflowExecution $execution): WorkflowClientInterface
    {
        $response = new GetWorkflowExecutionHistoryResponse(['history' => self::deterministicHistory()]);
        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects($this->once())
            ->method('getWorkflowHistory')
            ->with($execution)
            ->willReturn(new WorkflowExecutionHistory(Paginator::createFromGenerator((static fn() => yield [$response])(), null)));

        return $client;
    }
}
