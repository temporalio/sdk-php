<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Replay;

use PHPUnit\Framework\MockObject\MockObject;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;
use Temporal\DataConverter\DataConverter;
use Temporal\Testing\Replay\ReplayWorkflowTaskHandler;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Testing\Replay\WorkflowTaskHandlerResult;
use Temporal\Tests\Unit\Declaration\Fixture\SimpleWorkflow;
use Temporal\Tests\Unit\UnitTestCase;

final class WorkflowReplayerTestCase extends UnitTestCase
{
    private WorkflowReplayer $replayer;
    /** @var MockObject|ReplayWorkflowTaskHandler|ReplayWorkflowTaskHandler&MockObject  */
    private $taskHandler;

    protected function setUp(): void
    {
        $this->taskHandler = $this->createMock(ReplayWorkflowTaskHandler::class);
        $this->replayer = new WorkflowReplayer(DataConverter::createDefault(), $this->taskHandler);
        parent::setUp();
    }

    public function testHistoryIsReplayedFromFile(): void
    {
        $taskHandlerResult = new WorkflowTaskHandlerResult(
            'WorkflowType',
            new RespondWorkflowTaskCompletedRequest(),
            null,
            (new RespondQueryTaskCompletedRequest())
                ->setQueryResult(
                    (new Payloads())->setPayloads(
                        [(new Payload())->setData('"hello"')->setMetadata(['encoding' => 'json/plain'])]
                    )
                ),
            false
        );

        $this->taskHandler
            ->expects($this->once())
            ->method('handleWorkflowTask')
            ->willReturn($taskHandlerResult);

        $result = $this->replayer->replayWorkflowExecutionFromFile(__DIR__ . '/history.json', SimpleWorkflow::class);
        $this->assertSame('hello', $result->getValue(0));
    }
}
