<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Activity\ActivityInfo;

class ActivityInfoTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new ActivityInfo();

        $expected = [
            'TaskToken'         => \base64_encode('00000000-0000-0000-0000-000000000000'),
            'WorkflowType'      => null,
            'WorkflowNamespace' => 'default',
            'WorkflowExecution' => null,
            'ActivityID'        => '0',
            'ActivityType'      => [
                'Name' => '',
            ],
            'TaskQueue'         => 'default',
            'HeartbeatTimeout'  => 0,
            'ScheduledTime'     => $dto->scheduledTime->toRfc3339String(),
            'StartedTime'       => $dto->startedTime->toRfc3339String(),
            'Deadline'          => $dto->deadline->toRfc3339String(),
            'Attempt'           => 1,
            'Priority' => [
                'priority_key' => 0,
                'fairness_key' => '',
                'fairness_weight' => 0.0,
            ],
            'RetryPolicy' => null,
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
