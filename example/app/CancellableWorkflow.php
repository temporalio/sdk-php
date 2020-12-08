<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Promise;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\WorkflowInterface;
use Temporal\Client\Workflow\WorkflowMethod;

#[WorkflowInterface]
class CancellableWorkflow
{
    private ActivityOptions $options;

    public function __construct()
    {
        $this->options = new ActivityOptions();
        $this->options->startToCloseTimeout = DateInterval::parse('10s');
    }

    #[WorkflowMethod(name: 'CancellableWorkflow')]
    public function handle()
    {
        $first = Workflow::newCancellationScope(function () {
            $activities = Workflow::newActivityStub(SimpleActivity::class, $this->options);

            return yield $activities->echo(42);
        });

        $second = Workflow::newCancellationScope(function () {
            $activities = Workflow::newActivityStub(SimpleActivity::class, $this->options);

            return yield $activities->echo(23);
        });

        $result = yield Promise::any([$first, $second]);

        $first->cancel();
        $second->cancel();

        return 0xDEAD_BEEF;
    }
}
