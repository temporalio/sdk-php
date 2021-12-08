<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

/**
 * @internal
 */
final class ClientMock implements ClientInterface
{
    private const ERROR_REQUEST_ID_DUPLICATION =
        'Unable to create a new request because a ' .
        'request with id %d has already been sent';

    private const ERROR_REQUEST_NOT_FOUND =
        'Unable to receive a request with id %d because ' .
        'a request with that identifier was not sent';

    private QueueInterface $queue;
    private array $requests = [];

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @psalm-param SuccessResponseInterface|FailureResponseInterface $response
     * @param ResponseInterface $response
     */
    public function dispatch(ResponseInterface $response): void
    {
        if (!isset($this->requests[$response->getID()])) {
            throw new \LogicException(sprintf('Got the response to undefined request %s', $response->getID()));
        }

        $deferred = $this->fetch($response->getID());

        if ($response instanceof FailureResponseInterface) {
            $deferred->reject($response->getFailure());
        } else {
            $deferred->resolve($response->getPayloads());
        }
    }

    public function request(RequestInterface $request): PromiseInterface
    {
        $this->queue->push($request);

        $id = $request->getID();

        if (isset($this->requests[$id])) {
            throw new \OutOfBoundsException(\sprintf(self::ERROR_REQUEST_ID_DUPLICATION, $id));
        }

        $this->requests[$id] = $deferred = new Deferred();

        return $deferred->promise();
    }

    /**
     * Check if command still in sending queue.
     */
    public function isQueued(CommandInterface $command): bool
    {
        return $this->queue->has($command->getID());
    }

    public function cancel(CommandInterface $command): void
    {
        // remove from queue
        $this->queue->pull($command->getID());
        $this->fetch($command->getID())->reject(new CanceledFailure('internal cancel'));
    }

    /**
     * Reject pending promise.
     */
    public function reject(CommandInterface $command, \Throwable $reason): void
    {
        $request = $this->fetch($command->getID());
        $request->reject($reason);
    }

    private function fetch(int $id): Deferred
    {
        $request = $this->get($id);

        try {
            return $request;
        } finally {
            unset($this->requests[$id]);
        }
    }

    private function get(int $id): Deferred
    {
        if (!isset($this->requests[$id])) {
            throw new \UnderflowException(\sprintf(self::ERROR_REQUEST_NOT_FOUND, $id));
        }

        return $this->requests[$id];
    }
}
