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
    public function handler(WorkflowContextInterface $context)
    {

        return ['runid' => $context->getRunId()];
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
