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
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Workflow\Command\CompleteWorkflow;
use Temporal\Client\Workflow\Command\ExecuteActivity;
use Temporal\Client\Workflow\Command\NewTimer;

/**
 * @mixin WorkflowRequestsInterface
 */
trait WorkflowRequestsTrait
{
    /**
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        return $this->request(
            new CompleteWorkflow($result)
        );
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    abstract protected function request(RequestInterface $request): PromiseInterface;

    /**
     * {@inheritDoc}
     */
    public function executeActivity(string $name, array $arguments = [], $options = null): PromiseInterface
    {
        $request = new ExecuteActivity($name, $arguments, ActivityOptions::new($options));

        return $this->request($request);
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    public function timer($interval): PromiseInterface
    {
        $request = new NewTimer(NewTimer::parseInterval($interval));

        return $this->request($request);
    }
}
