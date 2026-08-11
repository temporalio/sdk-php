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
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Waits (with no timer) for a signal, then calls a mocked activity with a start-to-close
 * timeout. Exercises the #745 interaction: a client-driven time advance must not fast-forward
 * through the activity timeout before the mocked response arrives.
 */
#[WorkflowInterface]
class SignalThenMockedActivityWorkflow
{
    private bool $go = false;

    #[WorkflowMethod(name: 'SignalThenMockedActivityWorkflow')]
    public function handler(): iterable
    {
        yield Workflow::await(fn(): bool => $this->go);

        $activity = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        return yield $activity->echo('ping');
    }

    #[SignalMethod(name: 'go')]
    public function go(): void
    {
        $this->go = true;
    }

    #[QueryMethod(name: 'ready')]
    public function ready(): bool
    {
        return true;
    }
}
