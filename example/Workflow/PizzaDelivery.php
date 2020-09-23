<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Workflow;

use Temporal\Client\Declaration\Workflow;
use Temporal\Client\Declaration\WorkflowInterface;
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
     */
    public function handler(WorkflowContextInterface $context)
    {
        $result = yield $context->executeActivity('ExecuteActivity');

        return [$result];
    }

    /**
     * @return WorkflowInterface
     */
    public static function toWorkflow(): WorkflowInterface
    {
        $handler = [new static(), 'handler'];

        return new Workflow('PizzaDelivery', \Closure::fromCallable($handler));
    }
}
