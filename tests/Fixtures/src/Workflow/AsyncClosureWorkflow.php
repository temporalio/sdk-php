<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class AsyncClosureWorkflow
{
    private array $result = [];

    #[WorkflowMethod]
    public function handler()
    {
        $promise = Workflow::async(
            function (): void {
                Workflow::async(fn() => $this->result[] = 'before')->await();
                Workflow::awaitWithTimeout(999, static fn() => false);
                Workflow::async(fn() => $this->result[] = 'after')->await();
            },
        );

        Workflow::async(
            function () use ($promise): void {
                Workflow::await(fn() => \count($this->result) === 1);
                Workflow::timer(1);
                $promise->cancel();
            },
        )->await();

        try {
            $promise->await();
        } catch (CanceledFailure $exception) {
        }

        return \implode(' ', $this->result);
    }
}
