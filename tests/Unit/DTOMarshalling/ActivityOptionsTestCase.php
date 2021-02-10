<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTOMarshalling;

use Temporal\Activity\ActivityOptions;

class ActivityOptionsTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new ActivityOptions();

        $expected = [
            'TaskQueueName'          => 'default',
            'ScheduleToCloseTimeout' => 0,
            'ScheduleToStartTimeout' => 0,
            'StartToCloseTimeout'    => 0,
            'HeartbeatTimeout'       => 0,
            'WaitForCancellation'    => false,
            'ActivityID'             => '',
            'RetryPolicy'            => [
                'initial_interval'          => null,
                'backoff_coefficient'       => 2.0,
                'maximum_interval'          => null,
                'maximum_attempts'          => 1,
                'non_retryable_error_types' => [],
            ],
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
