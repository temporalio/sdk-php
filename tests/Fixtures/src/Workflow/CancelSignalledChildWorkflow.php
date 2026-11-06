<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use React\Promise\Deferred;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class CancelSignalledChildWorkflow
{
    private array $status = [];

    #[Workflow\QueryMethod(name: 'getStatus')]
    public function getStatus(): array
    {
        return $this->status;
    }

    #[WorkflowMethod(name: 'CancelSignalledChildWorkflow')]
    public function handler()
    {
        // typed stub
        $simple = Workflow::newChildWorkflowStub(SimpleSignalledWorkflow::class);

        $waitSignalled = new Deferred();

        $this->status[] = 'start';

        // start execution
        $scope = Workflow::async(
            function () use ($simple, $waitSignalled) {
                $call = Workflow::async(static fn() => $simple->handler());
                $this->status[] = 'child started';

                $simple->add(8);
                $this->status[] = 'child signalled';
                $waitSignalled->resolve(null);

                return $call->await();
            },
        );

        // only cancel scope when signal dispatched
        Workflow::await($waitSignalled->promise());
        $scope->cancel();
        $this->status[] = 'scope cancelled';

        try {
            return $scope->await();
        } catch (\Throwable $e) {
            $this->status[] = 'process done';

            return 'cancelled ok';
        }
    }
}
