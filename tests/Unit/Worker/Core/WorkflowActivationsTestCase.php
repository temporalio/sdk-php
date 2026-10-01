<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Coresdk\Child_workflow\ChildWorkflowResult;
use Coresdk\Child_workflow\Success as ChildSuccess;
use Coresdk\Workflow_activation\InitializeWorkflow;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecution;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStart;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartFailure;
use Coresdk\Workflow_activation\ResolveChildWorkflowExecutionStartSuccess;
use Coresdk\Workflow_activation\WorkflowActivation;
use Coresdk\Workflow_activation\WorkflowActivationJob;
use Coresdk\Workflow_commands\ActivityCancellationType;
use Coresdk\Workflow_commands\WorkflowCommand;
use Coresdk\Workflow_completion\WorkflowActivationCompletion;
use PHPUnit\Framework\TestCase;
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\DataConverter\DataConverter;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Worker\Core\PayloadMapper;
use Temporal\Worker\Core\ResolutionMapper;
use Temporal\Worker\Core\RunState;
use Temporal\Worker\Core\WorkflowActivations;
use Temporal\Worker\Transport\Command\Client\Request;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;

final class WorkflowActivationsTestCase extends TestCase
{
    public function testTypedSearchAttributesUseMetadataNames(): void
    {
        $commands = $this->start([new Request('UpsertWorkflowTypedSearchAttributes', ['search_attributes' => [
            'Kw' => ['type' => ValueType::Keyword->value, 'operation' => 'set', 'value' => 'v'],
            'Num' => ['type' => ValueType::Float->value, 'operation' => 'set', 'value' => 1.5],
            'Gone' => ['type' => ValueType::Int->value, 'operation' => 'unset'],
        ]])]);

        $fields = $commands[0]->getUpsertWorkflowSearchAttributes()->getSearchAttributes()->getIndexedFields();
        self::assertSame('Keyword', $fields['Kw']->getMetadata()['type']);
        self::assertSame('Double', $fields['Num']->getMetadata()['type']);
        self::assertFalse(isset($fields['Gone']->getMetadata()['type']));
    }

    public function testUnknownSearchAttributeTypeFailsTheActivation(): void
    {
        $completion = $this->complete($this->activations(static fn(array $messages): array => $messages[0] instanceof ServerRequest
            ? [new Request('UpsertWorkflowTypedSearchAttributes', ['search_attributes' => [
                'Bad' => ['type' => 'decimal', 'operation' => 'set', 'value' => 1],
            ]])]
            : []), [self::initialize()]);

        self::assertSame('failed', $completion->getStatus());
        self::assertStringContainsString('"decimal"', $completion->getFailed()->getFailure()->getMessage());
    }

    public function testNegativeTimerFailsLikeRoadRunner(): void
    {
        $responses = [];
        $this->complete($this->activations(static function (array $messages) use (&$responses): array {
            if ($messages[0] instanceof ServerRequest) {
                return [new Request('NewTimer', ['ms' => -5])];
            }
            \array_push($responses, ...$messages);
            return [];
        }), [self::initialize()]);

        self::assertCount(1, $responses);
        self::assertInstanceOf(FailureResponse::class, $responses[0]);
        self::assertInstanceOf(ApplicationFailure::class, $responses[0]->getFailure());
        self::assertSame('negative duration provided -5ms', $responses[0]->getFailure()->getOriginalMessage());
    }

    public function testZeroTimerResolvesWithoutCommand(): void
    {
        $responses = [];
        $completion = $this->complete($this->activations(static function (array $messages) use (&$responses): array {
            if ($messages[0] instanceof ServerRequest) {
                return [new Request('NewTimer', ['ms' => 0])];
            }
            \array_push($responses, ...$messages);
            return [];
        }), [self::initialize()]);

        self::assertCount(0, $completion->getSuccessful()->getCommands());
        self::assertInstanceOf(SuccessResponse::class, $responses[0]);
    }

    public function testLocalActivityWaitsForCancellationCompleted(): void
    {
        $commands = $this->start([new Request('ExecuteLocalActivity', ['name' => 'Act', 'options' => []])]);

        self::assertSame(
            ActivityCancellationType::WAIT_CANCELLATION_COMPLETED,
            $commands[0]->getScheduleLocalActivity()->getCancellationType(),
        );
    }

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

    private static function initialize(): WorkflowActivationJob
    {
        return new WorkflowActivationJob(['initialize_workflow' => new InitializeWorkflow([
            'workflow_type' => 'Wf',
            'workflow_id' => 'wf-id',
            'attempt' => 1,
        ])]);
    }

    /**
     * @param list<CommandInterface> $commands
     * @return list<WorkflowCommand>
     */
    private function start(array $commands): array
    {
        $completion = $this->complete($this->activations(
            static fn(array $messages): array => $messages[0] instanceof ServerRequest ? $commands : [],
        ), [self::initialize()]);
        self::assertSame('successful', $completion->getStatus(), $completion->serializeToJsonString());

        return \iterator_to_array($completion->getSuccessful()->getCommands());
    }

    /**
     * @param list<WorkflowActivationJob> $jobs
     */
    private function complete(WorkflowActivations $activations, array $jobs): WorkflowActivationCompletion
    {
        $activation = new WorkflowActivation(['run_id' => 'run-id', 'jobs' => $jobs]);
        $completion = new WorkflowActivationCompletion();
        $completion->mergeFromString($activations->handle($activation->serializeToString()));

        return $completion;
    }

    private function activations(\Closure $dispatch): WorkflowActivations
    {
        return new WorkflowActivations(DataConverter::createDefault(), $dispatch, 'ns', 'queue', []);
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
