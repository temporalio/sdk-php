<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Common\Uuid;

class ActivityOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new ActivityOptions();

        $expected = [
            'TaskQueueName'          => null,
            'ScheduleToCloseTimeout' => 0,
            'ScheduleToStartTimeout' => 0,
            'StartToCloseTimeout'    => 0,
            'HeartbeatTimeout'       => 0,
            'WaitForCancellation'    => false,
            'ActivityID'             => '',
            'RetryPolicy'            => null,
            'Priority' => [
                'priority_key' => 0,
                'fairness_key' => '',
                'fairness_weight' => 0.0,
            ],
            'Summary' => '',
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }

    public function testTaskQueueChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withTaskQueue(Uuid::v4()));
    }

    public function testScheduleToCloseTimeoutChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withScheduleToCloseTimeout(
            CarbonInterval::days(42)
        ));
    }

    public function testScheduleToStartTimeoutChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withScheduleToStartTimeout(
            CarbonInterval::days(42)
        ));
    }

    public function testStartToCloseTimeoutChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withStartToCloseTimeout(
            CarbonInterval::days(42)
        ));
    }

    public function testHeartbeatTimeoutChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withHeartbeatTimeout(
            CarbonInterval::days(42)
        ));
    }

    public function testCancellationTypeChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withCancellationType(
            ActivityCancellationType::ABANDON
        ));
    }

    public function testCancellationTypeChangesNotMutateStateUsingEnum(): void
    {
        $dto = new ActivityOptions();

        $new = $dto->withCancellationType(ActivityCancellationType::WaitCancellationCompleted);
        $this->assertNotSame($dto, $new);
        $this->assertSame(ActivityCancellationType::WAIT_CANCELLATION_COMPLETED, $new->cancellationType);
    }

    public function testActivityIdChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withActivityId(
            Uuid::v4()
        ));
    }

    public function testRetryOptionsChangesNotMutateState(): void
    {
        $dto = new ActivityOptions();

        $this->assertNotSame($dto, $dto->withRetryOptions(
            RetryOptions::new()
        ));
    }
}
