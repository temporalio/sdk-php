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
use Temporal\Client\Protocol\Message\RequestInterface;
use Temporal\Client\Protocol\Request\CompleteWorkflow;
use Temporal\Client\Protocol\Request\ExecuteActivity;
use Temporal\Client\Runtime\Queue\RequestQueueInterface;

/**
 * @psalm-type WorkflowContextParams = array {
 *      name: string,
 *      wid: string,
 *      rid: string,
 *      taskQueue?: string,
 *      payload?: mixed,
 * }
 */
class WorkflowContext implements WorkflowContextInterface
{
    /**
     * @psalm-var WorkflowContextParams
     * @var array
     */
    private array $params;

    /**
     * @var RequestQueueInterface
     */
    private $queue;

    /**
     * @psalm-param WorkflowContextParams $params
     *
     * @param array $params
     * @param RequestQueueInterface $queue
     */
    public function __construct(array $params, RequestQueueInterface $queue)
    {
        $this->queue = $queue;
        $this->params = $params;
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string
    {
        return $this->params['wid'] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getRunId(): string
    {
        return $this->params['rid'] ?? 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkerId(): string
    {
        return $this->params['taskQueue'] ?? 'unknown';
    }

    /**
     * @return mixed|null
     */
    public function getPayload()
    {
        return $this->params['payload'] ?? null;
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    private function persist(RequestInterface $request): PromiseInterface
    {
        return $this->queue->add($request);
    }

    /**
     * @param mixed $result
     * @return PromiseInterface
     */
    public function complete($result = null): PromiseInterface
    {
        return $this->persist(new CompleteWorkflow($this, $result));
    }

    /**
     * @param string $name
     * @param array $arguments
     * @param ActivityOptions|null $opt
     * @return PromiseInterface
     */
    public function executeActivity(string $name, array $arguments = [], ActivityOptions $opt = null): PromiseInterface
    {
        return $this->persist(new ExecuteActivity($this, $name, $arguments, $opt));
    }
}
