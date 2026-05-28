<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class LoopSignallingWorkflow
{
    #[WorkflowMethod]
    public function run(
        WorkflowExecution $execution,
        bool $truncateRunID = false
    ) {
        if ($truncateRunID) {
            $execution = new WorkflowExecution($execution->getID());
        }

        $loop = Workflow::newExternalWorkflowStub(LoopWorkflow::class, $execution);
        yield $loop->addValue('loop');

        return 'OK';
    }
}
