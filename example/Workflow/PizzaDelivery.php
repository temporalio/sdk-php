<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Workflow;

use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;
use Temporal\Client\Workflow\Runtime\WorkflowContextInterface;

class PizzaDelivery
{
    /**
     * @WorkflowMethod(name="PizzaDelivery")
     */
    #[WorkflowMethod(name: 'PizzaDelivery')]
    public function handler(WorkflowContextInterface $ctx)
    {
        [$a, $b] = yield all([
            $ctx->executeActivity('A'),
            $ctx->executeActivity('B')
        ]);

        var_dump($ctx->now()->format(\DateTime::RFC3339));
        sleep(1);

        return 32;
    }

    /**
     * @QueryMethod()
     */
    #[QueryMethod]
    public function getStatus(): string
    {
        return 'I\'m Ok';
    }

    /**
     * @SignalMethod()
     */
    #[SignalMethod]
    public function retryNow(): void
    {
        //
    }
}
