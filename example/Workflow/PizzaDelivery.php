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
    public function handler(Workflow\WorkflowContextInterface $ctx, $input)
    {

        $result = yield from Coroutine::cooperative([
            $this->test('FIRST','Cyril'),
            $this->test('SECOND','Antony'),
            $this->test2('THIRD', $input),
        ]);

        return $result;
    }

    private function test($index, $value)
    {
        // Cyril from a
        // Antony from a
        $a = yield Workflow::activity(ExampleActivity::class)
            ->a($value)
        ;

        // cyril from b
        // antony from b
        $b = yield Workflow::activity(ExampleActivity::class)
            ->b($value)
        ;

        // FIRST: cyril from a from b
        // SECOND: antony from a from b
        return $index .': '. $a . $b;
    }

    private function test2($index, $value)
    {
        return yield Workflow::activity(ExampleActivity::class)
            ->a($value)
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
