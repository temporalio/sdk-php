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
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ContinuaWithTaskQueueWorkflow
{
    #[WorkflowMethod(name: 'ContinuaWithTaskQueueWorkflow')]
    public function handler(
        int $generation
    ) {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)
        );

        if ($generation > 5) {
            // complete
            return Workflow::getInfo()->taskQueue . $generation;
        }

        if ($generation !== 1) {
            assert(!empty(Workflow::getInfo()->continuedExecutionRunId));
        }

        for ($i = 0; $i < $generation; $i++) {
            yield $simple->echo((string)$generation);
        }

        return Workflow::newContinueAsNewStub(self::class)->handler(++$generation);
    }
}
