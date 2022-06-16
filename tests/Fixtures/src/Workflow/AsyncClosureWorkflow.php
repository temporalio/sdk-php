<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;

#[Workflow\WorkflowInterface]
class AsyncClosureWorkflow
{
    private array $result = [];

    #[WorkflowMethod()]
    public function handler()
    {
        $promise = Workflow::async(
            function (): \Generator {
                yield Workflow::async(fn() => $this->result[] = 'before');
                yield Workflow::awaitWithTimeout(999, fn() => false);
                yield Workflow::async(fn() => $this->result[] = 'after');
            }
        );

        yield Workflow::async(
            function () use ($promise): \Generator {
                yield Workflow::timer(1);
                $promise->cancel();
            }
        );

        return implode(' ', $this->result);
    }
}
