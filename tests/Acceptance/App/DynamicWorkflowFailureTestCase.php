<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Testing\Transcript\TranscriptLine;

abstract class DynamicWorkflowFailureTestCase extends TestCase
{
    protected function assertHandlerFailure(
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        string $probeType,
        string $message,
        string $cause = \TypeError::class,
    ): void {
        try {
            $stub->getResult(timeout: 5);
            self::fail('An invalid dynamic handler must fail the workflow.');
        } catch (WorkflowFailedException $exception) {
            $failure = $exception->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertSame(InvalidArgumentException::class, $failure->getType());
            self::assertSame($message, $failure->getOriginalMessage());
            self::assertInstanceOf(ApplicationFailure::class, $failure->getPrevious());
            self::assertSame($cause, $failure->getPrevious()->getType());
        }

        $starts = \array_values(\array_filter(
            $this->readCurrentTestTranscript(),
            static fn(TranscriptLine $line): bool => $line->getAttribute('event') === 'workflow_execute_start'
                && $line->getAttribute('workflow_id') === $stub->getExecution()->getID(),
        ));
        self::assertCount(1, $starts);

        $events = [];
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            $events[] = $event->getEventType();
        }
        self::assertContains(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED, $events);
        self::assertNotContains(EventType::EVENT_TYPE_WORKFLOW_TASK_FAILED, $events);

        $probe = $client->newUntypedWorkflowStub(
            $probeType,
            WorkflowOptions::new()->withTaskQueue($stub->getOptions()->taskQueue),
        );
        $client->start($probe);
        self::assertSame($starts[0]->processId, $probe->getResult('int', timeout: 5));
    }
}
