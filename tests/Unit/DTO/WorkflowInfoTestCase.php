<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Workflow\WorkflowInfo;

class WorkflowInfoTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkflowInfo();

        $expected = [
            'WorkflowExecution' => [
                'ID' => '00000000-0000-0000-0000-000000000000',
                'RunID' => null,
            ],
            'WorkflowType' => [
                'Name' => ''
            ],
            'TaskQueueName' => 'default',
            'WorkflowExecutionTimeout' => 290304000000000000,
            'WorkflowRunTimeout' => 290304000000000000,
            'WorkflowTaskTimeout' => 290304000000000000,
            'Namespace' => 'default',
            'Attempt' => 1,
            'HistoryLength' => 0,
            'CronSchedule' => null,
            'ContinuedExecutionRunID' => null,
            'ParentWorkflowNamespace' => null,
            'ParentWorkflowExecution' => null,
            'SearchAttributes' => null,
            'Memo' => null,
            'BinaryChecksum' => '',
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
