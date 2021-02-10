<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTOMarshalling;

use Temporal\Workflow\WorkflowType;

class WorkflowTypeTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkflowType();

        $expected = [
            'Name' => ''
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }
}
