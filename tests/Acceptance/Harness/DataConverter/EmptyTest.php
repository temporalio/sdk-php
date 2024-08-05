<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\DataConverter\Empty;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class EmptyTest extends TestCase
{
    #[Test]
    public function check(
        #[Stub('HarnessWorkflow_DataConverter_Empty')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        // verify the workflow returns nothing
        $result = $stub->getResult();
        self::assertNull($result);

        // get result payload of ActivityTaskScheduled event from workflow history
        $found = false;
        $event = null;
        /** @var HistoryEvent $event */
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if ($event->getEventType() === EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Activity task scheduled event not found');
        $payload = $event->getActivityTaskScheduledEventAttributes()?->getInput()?->getPayloads()[0];
        self::assertInstanceOf(Payload::class, $payload);
        \assert($payload instanceof Payload);

        $decoded = \json_decode('{ "metadata": { "encoding": "YmluYXJ5L251bGw=" } }', true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals($decoded, \json_decode($payload->serializeToJsonString(), true, 512, JSON_THROW_ON_ERROR));
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('HarnessWorkflow_DataConverter_Empty')]
    public function run()
    {
        yield Workflow::newActivityStub(
            EmptyActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(10),
        )->nullActivity(null);
    }
}

#[ActivityInterface]
class EmptyActivity
{
    /**
     * @return PromiseInterface<void>
     */
    #[ActivityMethod('null_activity')]
    public function nullActivity(?string $input): void
    {
        // check the null input is serialized correctly
        if ($input !== null) {
            throw new ApplicationFailure('Activity input should be null', 'BadResult', true);
        }
    }
}
