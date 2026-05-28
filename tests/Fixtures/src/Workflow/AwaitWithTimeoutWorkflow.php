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
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class AwaitWithTimeoutWorkflow
{
    #[WorkflowMethod()]
    public function handler()
    {
        yield Workflow::awaitWithTimeout(
            999,
            fn() => false,
        );

        yield Workflow::awaitWithTimeout(
            20,
            Workflow::awaitWithTimeout(500, fn() => false),
            Workflow::awaitWithTimeout(120, fn() => false),
        );

        return 'ok';
    }
}
