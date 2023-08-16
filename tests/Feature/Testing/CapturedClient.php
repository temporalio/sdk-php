<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Feature\Testing;

use React\Promise\PromiseInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\WorkflowInfo;

class CapturedClient implements ClientInterface
{
    /**
     * @var array<positive-int, PromiseInterface>
     */
    public array $requests = [];

    /**
     * @var ClientInterface
     */
    protected ClientInterface $parent;

    /**
     * @param ClientInterface $parent
     */
    public function __construct(ClientInterface $parent)
    {
        $this->parent = $parent;
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request, ?WorkflowInfo $workflowInfo = null): PromiseInterface
    {
        return $this->requests[$request->getID()] = $this->parent->request($request)
            ->then($this->onFulfilled($request), $this->onRejected($request));
    }

    /**
     * @param RequestInterface $request
     * @return \Closure
     */
    private function onFulfilled(RequestInterface $request): \Closure
    {
        return function ($response) use ($request) {
            unset($this->requests[$request->getID()]);

            return $response;
        };
    }

    /**
     * @param RequestInterface $request
     * @return \Closure
     * @psalm-suppress UnusedClosureParam
     */
    private function onRejected(RequestInterface $request): \Closure
    {
        return function (\Throwable $error) use ($request) {
            unset($this->requests[$request->getID()]);

            throw $error;
        };
    }

    /**
     * {@inheritDoc}
     */
    public function fetchUnresolvedRequests(): array
    {
        try {
            return $this->requests;
        } finally {
            $this->requests = [];
        }
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->requests);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->requests);
    }

    public function isQueued(CommandInterface $command): bool
    {
        return $this->parent->isQueued($command);
    }

    public function cancel(CommandInterface $command): void
    {
        $this->parent->cancel($command);
    }

    public function reject(CommandInterface $command, \Throwable $reason): void
    {
        $this->parent->reject($command, $reason);
    }
}
