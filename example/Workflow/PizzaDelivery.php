<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Workflow;

use App\Activity\ExampleActivity;
use React\Promise\PromiseInterface;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

class PizzaDelivery
{
    private int $value = 0;

    /** @QueryMethod() */
    public function get(): int
    {
        return $this->value;
    }

    /** @SignalMethod() */
    public function add(int $value): void
    {
        $this->value += $value;
    }

    /** @WorkflowMethod(name="PizzaDelivery") */
    public function handler(): iterable
    {
        $activity = Workflow::newActivityStub(ExampleActivity::class);

        /** @var PromiseInterface $promise */
        $value = yield $activity->a('test');

//        ->then(function ($v) {
//            error_log("hello world");
//            return strtoupper($v);
//        });

        return $value;

//        $promise->then(function () {
//            dump(1);
//        });
//
//        yield Workflow::timer(1)
//            ->then(function () {
//                dump(2);
//            });
    }
}
