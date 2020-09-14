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
use Temporal\Client\Transport\Request\Request;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\TransportInterface;

class PizzaDelivery
{
    /**
     * @param TransportInterface $transport
     * @param WorkflowContextInterface $context
     */
    public function handler(TransportInterface $transport, WorkflowContextInterface $context): void
    {
        $transport->send(
            $this->executeActivityRequest('ExampleActivity', $context)
        )
            ->then(fn() => $transport->send(
                $this->completeRequest($context)
            ))
        ;
    }

    /**
     * @param string $name
     * @param WorkflowContextInterface $ctx
     * @return RequestInterface
     */
    private function executeActivityRequest(string $name, WorkflowContextInterface $ctx): RequestInterface
    {
        return new Request(
            'ExecuteActivity', [
                'name' => $name,
                'rid'  => $ctx->getRunId(),
            ]
        );
    }

    /**
     * @param WorkflowContextInterface $ctx
     * @return RequestInterface
     */
    private function completeRequest(WorkflowContextInterface $ctx): RequestInterface
    {
        return new Request(
            'CompleteWorkflow', [
                'rid'    => $ctx->getRunId(),
                'result' => 'yo!',
            ]
        );
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
