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
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;

#[Workflow\WorkflowInterface]
class WorkflowWithSignalledSteps
{
    #[WorkflowMethod(name: 'WorkflowWithSignalledSteps')]
    public function handler()
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5),
        );

        $value = 0;
        Workflow::registerQuery('value', static function () use (&$value) {
            return $value;
        });

        Workflow::await($this->promiseSignal('begin'));
        $value++;

        Workflow::await($this->promiseSignal('next1'));
        $value++;

        Workflow::await($this->promiseSignal('next2'));
        $value++;

        return $value;
    }

    // is this correct?
    private function promiseSignal(string $name): PromiseInterface
    {
        $signal = new Deferred();
        Workflow::registerSignal($name, static function ($value) use ($signal): void {
            $signal->resolve($value);
        });

        return $signal->promise();
    }
}
