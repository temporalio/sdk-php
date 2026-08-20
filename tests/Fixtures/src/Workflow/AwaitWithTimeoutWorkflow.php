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
class AwaitWithTimeoutWorkflow
{
    #[WorkflowMethod]
    public function handler()
    {
        Workflow::awaitWithTimeout(
            999,
            static fn() => false,
        );

        $longWait = Workflow::async(
            static fn(): bool => Workflow::awaitWithTimeout(500, static fn() => false),
        );
        $shortWait = Workflow::async(
            static fn(): bool => Workflow::awaitWithTimeout(120, static fn() => false),
        );

        Workflow::awaitWithTimeout(20, $longWait, $shortWait);

        return 'ok';
    }
}
