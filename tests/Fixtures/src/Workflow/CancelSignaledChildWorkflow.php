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
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class CancelSignaledChildWorkflow
{
    private array $status = [];

    #[QueryMethod(name: 'getStatus')]
    public function getStatus(): array
    {
        return $this->status;
    }

    #[WorkflowMethod(name: 'CancelSignaledChildWorkflow')]
    public function handler()
    {
        // typed stub
        $simple = Workflow::newChildWorkflowStub(SimpleSignaledWorkflow::class);

        $waitSignaled = new Deferred();

        $this->status[] = 'start';

        // start execution
        $scope = Workflow::async(
            function () use ($simple, $waitSignaled) {
                $call = $simple->handler();
                $this->status[] = 'child started';

                yield $simple->add(8);
                $this->status[] = 'child signaled';
                $waitSignaled->resolve(null);

                return yield $call;
            }
        );

        // only cancel scope when signal dispatched
        yield $waitSignaled;
        $scope->cancel();
        $this->status[] = 'scope canceled';

        try {
            return yield $scope;
        } catch (\Throwable $e) {
            $this->status[] = 'process done';

            return 'canceled ok';
        }
    }
}
