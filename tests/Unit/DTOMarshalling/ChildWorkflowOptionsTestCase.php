<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTOMarshalling;

use Temporal\Workflow\ChildWorkflowOptions;

class ChildWorkflowOptionsTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new ChildWorkflowOptions();

        $expected = [
            'Namespace'                => 'default',
            'WorkflowID'               => null,
            'TaskQueueName'            => 'default',
            'WorkflowExecutionTimeout' => 0,
            'WorkflowRunTimeout'       => 0,
            'WorkflowTaskTimeout'      => 0,
            'WaitForCancellation'      => false,
            'WorkflowIDReusePolicy'    => 2,
            'RetryPolicy'              => [
                'initial_interval'          => null,
                'backoff_coefficient'       => 2.0,
                'maximum_interval'          => null,
                'maximum_attempts'          => 1,
                'non_retryable_error_types' => [],
            ],
            'CronSchedule'             => null,
            'ParentClosePolicy'        => 1,
            'Memo'                     => null,
            'SearchAttributes'         => null,
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
