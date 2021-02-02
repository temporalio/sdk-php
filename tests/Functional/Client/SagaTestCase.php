<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Tests\Workflow\SagaWorkflow;

/**
 * @group client
 * @group functional
 */
class SagaTestCase extends ClientTestCase
{
    public function testGetResult()
    {
        $client = $this->createClient();
        $saga = $client->newWorkflowStub(SagaWorkflow::class);

        $run = $client->start($saga);

        try {
            $run->getResult();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
        }

        $this->assertHistoryContains(
            $client,
            $run->getExecution(),
            function (HistoryEvent $e) {
                if ($e->getEventType() === EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED) {
                    // compensation call
                    if (
                        $e->getActivityTaskScheduledEventAttributes()->getActivityType()->getName(
                        ) === 'SimpleActivity.lower'
                    ) {
                        return true;
                    }
                }

                return false;
            }
        );

        $this->assertHistoryContains(
            $client,
            $run->getExecution(),
            function (HistoryEvent $e) {
                if ($e->getEventType() === EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED) {
                    // compensation call
                    if (
                        $e->getActivityTaskScheduledEventAttributes()->getActivityType()->getName(
                        ) === 'SimpleActivity.prefix'
                    ) {
                        return true;
                    }
                }

                return false;
            }
        );
    }
}
