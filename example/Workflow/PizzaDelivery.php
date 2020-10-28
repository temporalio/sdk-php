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
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;
use Temporal\Client\Workflow\Runtime\WorkflowContextInterface;

class PizzaDelivery
{
    #[WorkflowMethod(name: 'PizzaDelivery')]
    public function handler(WorkflowContextInterface $ctx): iterable
    {
        $activity = $ctx->activity(ExampleActivity::class);

        $a = yield $activity->doSomething();
        $b = $activity->doSomethingElse();

        var_dump($a, $b);
    }

    #[QueryMethod]
    public function getStatus(): string
    {
        return 'I\'m Ok';
    }

    #[SignalMethod]
    public function retryNow(): void
    {
        //
    }
}
