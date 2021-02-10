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

class WorkerOptionsTestCase extends DTOMarshallingTestCase
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
            'StickyScheduleToStartTimeout' => null,
            'WorkerStopTimeout' => null,
            'EnableSessionWorker' => false,
            'SessionResourceID' => null,
            'MaxConcurrentSessionExecutionSize' => 1000,
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
