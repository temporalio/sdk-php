<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Workflow\ContinueAsNewOptions;

class ContinueAsNewOptionsTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new ContinueAsNewOptions();

        $expected = [
            'WorkflowRunTimeout'  => 0,
            'TaskQueueName'       => 'default',
            'WorkflowTaskTimeout' => 0,
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
