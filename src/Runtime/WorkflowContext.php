<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use React\Promise\PromiseInterface;
use Temporal\Client\Transport\Request\InputRequestInterface;
use Temporal\Client\Transport\Request\StartWorkflow;

class WorkflowContext implements WorkflowContextInterface
{
    /**
     * @var StartWorkflow
     */
    private StartWorkflow $request;

    /**
     * @param StartWorkflow $request
     */
    public function __construct(StartWorkflow $request)
    {
        $this->request = $request;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequest(): InputRequestInterface
    {
        return $this->request;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->request->get('wid');
    }

    /**
     * {@inheritDoc}
     */
    public function getRunId()
    {
        return $this->request->get('rid');
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkerId(): string
    {
        return $this->request->get('taskQueue');
    }

    /**
     * @return mixed|null
     */
    public function getPayload()
    {
        return $this->request->get('payload');
    }

    public function complete(): void
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    public function executeActivity(string $name, array $arguments = []): PromiseInterface
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
