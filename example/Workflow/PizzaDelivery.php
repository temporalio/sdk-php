<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Workflow;

use Temporal\Client\Meta\WorkflowMethod;
use Temporal\Client\Runtime\WorkflowContextInterface;

class PizzaDelivery
{
    /**
     * Promise-based example:
     *
     * <code>
     *  $context->executeActivity('ExecuteActivity')
     *      ->then(function ($result) use ($context) {
     *          $context->complete($result)
     *      });
     * </code>
     *
     * Coroutine-based example:
     *
     * <code>
     *  $result = yield $context->executeActivity('ExecuteActivity');
     *
     *  return $result;
     * </code>
     *
     * @param WorkflowContextInterface $context
     * @return \Generator
     *
     * @WorkflowMethod(name="PizzaDelivery")
     */
    #[WorkflowMethod(name: 'PizzaDelivery')]
    public function handler(WorkflowContextInterface $context)
    {
        $result = yield [
            $context->executeActivity('A'),
            $context->executeActivity('B'),
        ];

        return 42;
    }
}
