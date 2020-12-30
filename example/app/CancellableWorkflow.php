<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Support\DateInterval;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class CancellableWorkflow
{
    private ActivityOptions $options;

    public function __construct()
    {
        $this->options = new ActivityOptions();
        $this->options->startToCloseTimeout = DateInterval::parse('10s');
    }

    #[Workflow\SignalMethod(name: 'signal')]
    public function signal()
    {
    }

    #[Workflow\QueryMethod(name: 'query')]
    public function query()
    {
        return 42;
    }

    #[WorkflowMethod(name: 'CancellableWorkflow')]
    public function handle()
    {
        $first = Workflow::newCancellationScope(function () {
            $activities = Workflow::newActivityStub(SimpleActivity::class, $this->options);

            return yield $activities->echo('First Scope');
        });

        $second = Workflow::newCancellationScope(function () {
            $activities = Workflow::newActivityStub(SimpleActivity::class, $this->options);

            return yield $activities->echo('Second Scope');
        });

        $result = yield Promise::any([$first, $second]);

        $first->cancel();
        $second->cancel();

        return $result;
    }
}
