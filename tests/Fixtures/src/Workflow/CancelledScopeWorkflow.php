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
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class CancelledScopeWorkflow
{
    #[WorkflowMethod(name: 'CancelledScopeWorkflow')]
    public function handler()
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)
        );

        $cancelled = 'not';

        $scope = Workflow::async(
            function () use ($simple) {
                yield Workflow::timer(2);
                yield $simple->slow('hello');
            }
        )->onCancel(
            function () use (&$cancelled) {
                $cancelled = 'yes';
            }
        );

        yield Workflow::timer(1);
        $scope->cancel();

        return $cancelled;
    }
}
