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
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Workflow\Command\CompleteWorkflow;
use Temporal\Client\Workflow\Command\ExecuteActivity;
use Temporal\Client\Workflow\Command\NewTimer;
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
        return $this->protocol->request(
            new CompleteWorkflow($result)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(string $name, array $arguments = [], $options = null): PromiseInterface
    {
        return $this->protocol->request(
            new ExecuteActivity($name, $arguments, $this->createActivityOptions($options))
        );
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    public function timer($interval): PromiseInterface
    {
        return $this->protocol->request(
            new NewTimer($this->createTimerOption($interval))
        );
    }

    /**
     * @param mixed $interval
     * @return int
     * @throws \Exception
     */
    private function createTimerOption($interval): int
    {
        switch (true) {
            case \is_string($interval):
                $interval = new \DateInterval($interval);

            case $interval instanceof \DateInterval:
                return (int)($interval->f * 1000);

            case \is_int($interval):
                return $interval * 1000;

            case \is_float($interval):
                return (int)($interval * 1000);

            default:
                throw new \InvalidArgumentException('Unrecognized date interval format');
        }
    }

    /**
     * @param mixed $options
     * @return ActivityOptions
     */
    private function createActivityOptions($options): ActivityOptions
    {
        switch (true) {
            case $options === null:
                return new ActivityOptions();

            case \is_array($options):
                return ActivityOptions::fromArray($options);

            case $options instanceof ActivityOptions:
                return $options;

            default:
                throw new \InvalidArgumentException('Invalid options type');
        }
    }
}
