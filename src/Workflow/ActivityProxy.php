<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use React\Promise\PromiseInterface;

/**
 * @psalm-template Activity of object
 */
class ActivityProxy
{
    /**
     * @var string
     */
    private string $class;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $protocol;

    /**
     * @psalm-param class-string<Activity>
     *
     * @param string $class
     * @param WorkflowContextInterface $protocol
     */
    public function __construct(string $class, WorkflowContextInterface $protocol)
    {
        $this->class = $class;
        $this->protocol = $protocol;
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return PromiseInterface
     */
    public function call(string $method, array $arguments = []): PromiseInterface
    {
        // TODO
        $activity = $this->class . '::' . $method;

        return $this->protocol->executeActivity($activity, $arguments);
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return PromiseInterface
     */
    public function __call(string $method, array $arguments = []): PromiseInterface
    {
        return $this->call($method, $arguments);
    }
}
