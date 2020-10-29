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
use Temporal\Client\Coroutine;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

class PizzaDelivery
{
    /** @WorkflowMethod(name="PizzaDelivery") */
    public function handler()
    {
        dump('BEGIN');
        $result = yield from Coroutine::cooperative([
            $this->test('AAAAAAAA'),
            $this->test('BBBBBBBB'),
        ]);

        return 42;
    }

    private function test($value)
    {
        yield Workflow::activity(ExampleActivity::class)
            ->a($value)
        ;
        yield Workflow::activity(ExampleActivity::class)
            ->b($value)
        ;
    }

    /** @QueryMethod() */
    public function getStatus(): string
    {
        return 'I\'m Ok';
    }

    /** @SignalMethod() */
    public function retryNow(): void
    {
        //
    }
}
