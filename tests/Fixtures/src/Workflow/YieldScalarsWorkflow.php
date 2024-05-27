<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class YieldScalarsWorkflow
{
    #[WorkflowMethod(name: 'YieldScalarsWorkflow')]
    public function handler(array $toYield): iterable {
        $result = [];

        foreach ($toYield as $value) {
            $result[] = yield $value;
        }

        return $result;
    }
}
