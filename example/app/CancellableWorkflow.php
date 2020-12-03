<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Client\Promise;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\WorkflowInterface;
use Temporal\Client\Workflow\WorkflowMethod;

#[WorkflowInterface]
class CancellableWorkflow
{
    #[WorkflowMethod(name: 'CancellableWorkflow')]
    public function handle()
    {
        $first = yield Workflow::newCancellationScope(function () {
            $activities = Workflow::newActivityStub(SimpleActivity::class);

            return $activities->echo(42);
        });

        $second = Workflow::newCancellationScope(function () {
            $activities = Workflow::newActivityStub(SimpleActivity::class);

            return $activities->echo(23);
        });

        $result = yield Promise::any([$first, $second]);

        $first->cancel();
        $second->cancel();

        dump($result);

        return 0xDEAD_BEEF;
    }
}
