<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Workflow\WorkflowExecution;

class WorkflowExecutionTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkflowExecution();

        $expected = [
            'ID' => '00000000-0000-0000-0000-000000000000',
            'RunID' => null
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
