<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Testing\TemporalServer;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\AwaitWithTimeoutOptionsWorkflow;
use Temporal\Tests\Workflow\ConcurrentAwaitWithTimeoutOptionsWorkflow;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;

final class AwaitWithTimeoutOptionsTestCase extends TestCase
{
    private WorkflowClient $workflowClient;
    private DataConverterInterface $dataConverter;

    public function testTimerSummaryFromAwaitOptionsIsSentToServer(): void
    {
        /** @see AwaitWithTimeoutOptionsWorkflow::handler() */
        $run = $this->startAwait('await-timer-summary', 1);

        // The condition is never met, so the await is settled by the timer
        self::assertFalse($run->getResult('bool', 30));

        $timers = $this->timerStartedEvents($run->getExecution());
        self::assertCount(1, $timers);
        self::assertSame('await-timer-summary', $this->summaryOf($timers[0]));
    }

    public function testAwaitOptionsIntervalIsUsedAsTimerTimeout(): void
    {
        /** @see AwaitWithTimeoutOptionsWorkflow::handler() */
        $run = $this->startAwait('await-timer-interval', 3);

        self::assertFalse($run->getResult('bool', 30));

        $timers = $this->timerStartedEvents($run->getExecution());
        self::assertSame(
            3,
            (int) $timers[0]->getTimerStartedEventAttributes()?->getStartToFireTimeout()?->getSeconds(),
        );
    }

    public function testAwaitTimerIsFiredAndWorkflowCompleted(): void
    {
        /** @see AwaitWithTimeoutOptionsWorkflow::handler() */
        $run = $this->startAwait('await-timer-fired', 1);

        self::assertFalse($run->getResult('bool', 30));

        $types = $this->eventTypes($run->getExecution());
        self::assertContains(EventType::EVENT_TYPE_TIMER_STARTED, $types);
        self::assertContains(EventType::EVENT_TYPE_TIMER_FIRED, $types);
        self::assertContains(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED, $types);
        self::assertNotContains(EventType::EVENT_TYPE_TIMER_CANCELED, $types);
    }

    public function testConcurrentAwaitsHaveOwnTimerSummaries(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            ConcurrentAwaitWithTimeoutOptionsWorkflow::class,
            WorkflowOptions::new()->withWorkflowRunTimeout('30 seconds'),
        );

        /** @see ConcurrentAwaitWithTimeoutOptionsWorkflow::handler() */
        $run = $this->workflowClient->start($workflow, 'first-await', 'second-await');

        self::assertSame([false, false], $run->getResult('array', 30));

        $summaries = \array_map(
            $this->summaryOf(...),
            $this->timerStartedEvents($run->getExecution()),
        );

        \sort($summaries);
        self::assertSame(['first-await', 'second-await'], $summaries);
    }

    public function testAwaitOptionsWithoutTimerOptionsSendsNoSummary(): void
    {
        /** @see AwaitWithTimeoutOptionsWorkflow::handler() */
        $run = $this->startAwait(null, 1);

        self::assertFalse($run->getResult('bool', 30));

        $timers = $this->timerStartedEvents($run->getExecution());
        self::assertCount(1, $timers);
        self::assertNull($timers[0]->getUserMetadata()?->getSummary());
    }

    public function testPlainTimeoutSendsNoTimerSummary(): void
    {
        /** @see AwaitWithTimeoutOptionsWorkflow::handler() */
        $run = $this->startAwait(null, 1, useAwaitOptions: false);

        self::assertFalse($run->getResult('bool', 30));

        $timers = $this->timerStartedEvents($run->getExecution());
        self::assertCount(1, $timers);
        self::assertNull($timers[0]->getUserMetadata()?->getSummary());
    }

    protected function setUp(): void
    {
        $this->dataConverter = DataConverter::createDefault();
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create(TemporalServer::address()),
            converter: $this->dataConverter,
        );

        parent::setUp();
    }

    private function startAwait(
        ?string $summary,
        int $timeout,
        bool $useAwaitOptions = true,
    ): WorkflowRunInterface {
        $workflow = $this->workflowClient->newWorkflowStub(
            AwaitWithTimeoutOptionsWorkflow::class,
            WorkflowOptions::new()->withWorkflowRunTimeout('30 seconds'),
        );

        return $this->workflowClient->start($workflow, $summary, $timeout, $useAwaitOptions);
    }

    /**
     * @return list<HistoryEvent>
     */
    private function timerStartedEvents(WorkflowExecution $execution): array
    {
        $result = [];
        foreach ($this->workflowClient->getWorkflowHistory($execution, pageSize: 50) as $event) {
            $event->hasTimerStartedEventAttributes() and $result[] = $event;
        }

        $result === [] and $this->fail('Timer not found in the workflow history.');

        return $result;
    }

    /**
     * @return list<int>
     */
    private function eventTypes(WorkflowExecution $execution): array
    {
        $result = [];
        foreach ($this->workflowClient->getWorkflowHistory($execution, pageSize: 50) as $event) {
            $result[] = $event->getEventType();
        }

        return $result;
    }

    private function summaryOf(HistoryEvent $event): ?string
    {
        $payload = $event->getUserMetadata()?->getSummary();

        return $payload instanceof Payload
            ? (string) $this->dataConverter->fromPayload($payload, 'string')
            : null;
    }
}
