<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Child_workflow\ChildWorkflowResult;
use Coresdk\Child_workflow\Success as ChildSuccess;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecution;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStart;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartFailure;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartSuccess;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Worker\Core\PayloadMapper;
use Temporal\Worker\Core\ResolutionMapper;
use Temporal\Worker\Core\RunState;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\TickInfo;

final class WorkflowActivationsTestCase extends TestCase
{
    public function testChildStartFailureAnswersWaitersAndReleasesTheChild(): void
    {
        $run = new RunState('run');
        $seq = $run->bind(10, RunState::CHILD);
        $run->startChild(10, 'child-id');
        $run->awaitChildExecution(10, 11);

        $messages = $this->resolutions()->childStarted($run, new ResolveChildWorkflowExecutionStart([
            'seq' => $seq,
            'failed' => new ResolveChildWorkflowExecutionStartFailure(['workflow_id' => 'child-id', 'workflow_type' => 'Child']),
        ]), new TickInfo(new \DateTimeImmutable()));

        self::assertSame([10, 11], \array_map(static fn(CommandInterface $message): int => $message->getID(), $messages));
        self::assertInstanceOf(ChildWorkflowFailure::class, $messages[0]->getFailure());
        self::assertSame([[], [], []], $this->childState($run));
    }

    public function testChildResultReleasesTheStartedChild(): void
    {
        $run = new RunState('run');
        $seq = $run->bind(10, RunState::CHILD);
        $run->startChild(10, 'child-id');
        $resolutions = $this->resolutions();
        $tick = new TickInfo(new \DateTimeImmutable());

        $resolutions->childStarted($run, new ResolveChildWorkflowExecutionStart([
            'seq' => $seq,
            'succeeded' => new ResolveChildWorkflowExecutionStartSuccess(['run_id' => 'child-run']),
        ]), $tick);
        self::assertSame(['child-id', 'child-run'], $run->childExecution(10));

        $resolutions->childResult($run, new ResolveChildWorkflowExecution([
            'seq' => $seq,
            'result' => new ChildWorkflowResult(['completed' => new ChildSuccess()]),
        ]), $tick);

        self::assertSame([[], [], []], $this->childState($run));
    }

    private function resolutions(): ResolutionMapper
    {
        return new ResolutionMapper(new PayloadMapper(DataConverter::createDefault()), 'ns');
    }

    private function childState(RunState $run): array
    {
        return (fn(): array => [$this->childWorkflowIds, $this->childExecutions, $this->childWaiters])->call($run);
    }
}
