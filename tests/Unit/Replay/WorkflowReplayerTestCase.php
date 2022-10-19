<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Replay;

use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\Unit\Declaration\Fixture\SimpleWorkflow;
use Temporal\Tests\Unit\UnitTestCase;

final class WorkflowReplayerTestCase extends UnitTestCase
{
    private WorkflowReplayer $replayer;

    protected function setUp(): void
    {
        $this->replayer = new WorkflowReplayer();
        parent::setUp();
    }

    public function testHistoryIsReplayedFromFile(): void
    {
        $this->replayer->replayWorkflowExecutionFromFile(__DIR__ . '/history.json', SimpleWorkflow::class);
    }
}
