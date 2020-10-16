<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Workflow\Command\CompleteWorkflow;
use Temporal\Client\Workflow\Command\ExecuteActivity;
use Temporal\Client\Workflow\Protocol\WorkflowProtocolInterface;

/**
 * @mixin InteractWithQueueInterface
 */
trait InteractWithQueueTrait
{
    /**
     * @var WorkflowProtocolInterface
     */
    private WorkflowProtocolInterface $protocol;

    /**
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        return $this->protocol->request(new CompleteWorkflow($result));
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(string $name, array $arguments = []): PromiseInterface
    {
        return $this->protocol->request(new ExecuteActivity($name, $arguments));
    }
}
