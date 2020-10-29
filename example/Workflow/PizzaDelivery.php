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
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;
use Temporal\Client\Workflow\WorkflowContextInterface;

class PizzaDelivery
{
    /** @WorkflowMethod(name="PizzaDelivery") */
    public function handler(WorkflowContextInterface $ctx): iterable
    {
        $activity = $ctx->activity(ExampleActivity::class);

        yield $activity->doSomething(42);
        yield $activity->doSomethingElse();

        dump(Workflow::now()->format(\DateTime::RFC3339));

        return 42;
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
