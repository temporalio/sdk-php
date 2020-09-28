<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Workflow;

use Temporal\Client\Meta\QueryMethod;
use Temporal\Client\Meta\SignalMethod;
use Temporal\Client\Meta\WorkflowMethod;
use Temporal\Client\Runtime\WorkflowContextInterface;

class PizzaDelivery
{
    /**
     * @WorkflowMethod(name="PizzaDelivery")
     */
    #[WorkflowMethod(name: 'PizzaDelivery')]
    public function handler(WorkflowContextInterface $context)
    {
        $a = yield $context->executeActivity('A');
        $b = yield $context->executeActivity('B');

        return [$a, $b];
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
