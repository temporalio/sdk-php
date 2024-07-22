<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\CancelTryCancel;

use React\Promise\PromiseInterface;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Exception\Client\ActivityCanceledException;

#[ActivityInterface]
class FeatureActivity
{
    public function __construct(
        private readonly WorkflowClientInterface $client,
    ) {
    }

    /**
     * @return PromiseInterface<null>
     */
    #[ActivityMethod('cancellable_activity')]
    public function cancellableActivity()
    {
        # Heartbeat every second for a minute
        $result = 'timeout';
        try {
            for ($i = 0; $i < 5_0; $i++) {
                \usleep(100_000);
                Activity::heartbeat($i);
            }
        } catch (ActivityCanceledException $e) {
            $result = 'cancelled';
        } catch (\Throwable $e) {
            $result = 'unexpected';
        }

        # Send result as signal to workflow
        $execution = Activity::getInfo()->workflowExecution;
        $this->client
            ->newRunningWorkflowStub(FeatureWorkflow::class, $execution->getID(), $execution->getRunID())
            ->activityResult($result);
    }
}
