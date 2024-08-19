<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Worker\WorkerOptions;

class WorkerOptionsTestCase extends AbstractDTOMarshalling
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkerOptions();

        $expected = [
            'MaxConcurrentActivityExecutionSize' => 0,
            'WorkerActivitiesPerSecond' => 0.0,
            'MaxConcurrentLocalActivityExecutionSize' => 0,
            'WorkerLocalActivitiesPerSecond' => 0.0,
            'TaskQueueActivitiesPerSecond' => 0.0,
            'MaxConcurrentActivityTaskPollers' => 0,
            'MaxConcurrentWorkflowTaskExecutionSize' => 0,
            'MaxConcurrentWorkflowTaskPollers' => 0,
            'MaxConcurrentNexusTaskExecutionSize' => 0,
            'MaxConcurrentNexusTaskPollers' => 0,
            'EnableLoggingInReplay' => false,
            'StickyScheduleToStartTimeout' => null,
            'WorkflowPanicPolicy' => 0,
            'WorkerStopTimeout' => null,
            'EnableSessionWorker' => false,
            'SessionResourceID' => null,
            'MaxConcurrentSessionExecutionSize' => 1000,
            'DisableWorkflowWorker' => false,
            'LocalActivityWorkerOnly' => false,
            'Identity' => "",
            'DeadlockDetectionTimeout' => null,
            'MaxHeartbeatThrottleInterval' => null,
            'DisableEagerActivities' => false,
            'MaxConcurrentEagerActivityExecutionSize' => 0,
            'DisableRegistrationAliasing' => false,
            'BuildID' => "",
            'UseBuildIDForVersioning' => false,
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
