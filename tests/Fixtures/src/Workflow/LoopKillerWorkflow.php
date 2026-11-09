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

#[Workflow\WorkflowInterface]
class LoopKillerWorkflow
{
    #[Workflow\WorkflowMethod]
    public function run(
        Workflow\WorkflowExecution $execution,
        bool $truncateRunID = false,
    ) {
        if ($truncateRunID) {
            $execution = new Workflow\WorkflowExecution($execution->getID());
        }

        $loop = Workflow::newUntypedExternalWorkflowStub($execution);
        $loop->cancel();

        return 'OK';
    }
}
