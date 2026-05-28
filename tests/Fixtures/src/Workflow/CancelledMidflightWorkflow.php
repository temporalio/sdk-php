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
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class CancelledMidflightWorkflow
{
    private array $status = [];

    #[QueryMethod(name: 'getStatus')]
    public function getStatus(): array
    {
        return $this->status;
    }

    #[WorkflowMethod(name: 'CancelledMidflightWorkflow')]
    public function handler()
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)
        );

        $this->status[] = 'start';

        $scope = Workflow::async(
            function () use ($simple) {
                $this->status[] = 'in scope';
                $simple->slow('1');
            }
        )->onCancel(
            function () {
                $this->status[] = 'on cancel';
            }
        );

        $scope->cancel();
        $this->status[] = 'done cancel';

        return 'OK';
    }
}
