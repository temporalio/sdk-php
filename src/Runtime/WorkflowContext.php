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
use Temporal\Client\Transport\Request\Request;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\Request\StartWorkflow;
use Temporal\Client\Transport\TransportInterface;

class WorkflowContext implements WorkflowContextInterface
{
    /**
     * @var TransportInterface
     */
    public TransportInterface $transport;

    /**
     * @var StartWorkflow
     */
    private StartWorkflow $request;

    /**
     * @param StartWorkflow $request
     * @param TransportInterface $transport
     */
    public function __construct(StartWorkflow $request, TransportInterface $transport)
    {
        $this->request = $request;
        $this->transport = $transport;
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

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    private function send(RequestInterface $request): PromiseInterface
    {
        return $this->transport->send($request);
    }

    /**
     * @param mixed $result
     * @return PromiseInterface
     */
    public function complete($result = null): PromiseInterface
    {
        $request = new Request('CompleteWorkflow', [
            'rid'    => $this->getRunId(),
            'result' => $result,
        ]);

        return $this->send($request);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return PromiseInterface
     */
    public function executeActivity(string $name, array $arguments = []): PromiseInterface
    {
        $request = new Request('ExecuteActivity', [
            'name'      => $name,
            'rid'       => $this->getRunId(),
            'arguments' => $arguments,
        ]);

        return $this->send($request);
    }
}
