<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Exception\CancellationException;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ErrorResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;
use Temporal\Worker\LoopInterface;

/**
 * @internal Client is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Internal\Transport
 */
final class Client implements ClientInterface
{
    /**
     * @var string
     */
    private const ERROR_REQUEST_ID_DUPLICATION =
        'Unable to create a new request because a ' .
        'request with id %d has already been sent';

    /**
     * @var string
     */
    private const ERROR_REQUEST_NOT_FOUND =
        'Unable to receive a request with id %d because ' .
        'a request with that identifier was not sent';

    /**
     * @var QueueInterface
     */
    private QueueInterface $queue;

    /**
     * @var array|Deferred[]
     */
    private array $requests = [];

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @param QueueInterface $queue
     * @param LoopInterface $loop
     */
    public function __construct(QueueInterface $queue, LoopInterface $loop)
    {
        $this->queue = $queue;
        $this->loop = $loop;
    }

    /**
     * @psalm-param SuccessResponseInterface|ErrorResponseInterface $response
     * @param ResponseInterface $response
     */
    public function dispatch(ResponseInterface $response): void
    {
        if (!isset($this->requests[$response->getID()])) {
            error_log(sprintf("Got the response to undefined request %s", $response->getID()));
            return;
        }

        $deferred = $this->fetch($response->getID());

        if ($response instanceof ErrorResponseInterface) {
            $deferred->reject($response->getFailure());
        } else {
            $result = $response->getPayloads();

            // todo: make sure this is correct with arrays
            $deferred->resolve(\current($result) ?: false);
        }
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        $this->queue->push($request);

        $id = $request->getID();

        if (isset($this->requests[$id])) {
            throw new \OutOfBoundsException(\sprintf(self::ERROR_REQUEST_ID_DUPLICATION, $id));
        }

        $this->requests[$id] = $deferred = new Deferred(
            function () use ($id) {
                $request = $this->fetch($id);
                $request->reject(CancellationException::fromRequestId($id));

                // In the case that after the local promise rejection we have
                // nothing to send, then we independently execute the next
                // tick of the event loop.
                if ($this->queue->count() === 0) {
                    $this->loop->tick();
                }
            }
        );

        return $deferred->promise();
    }

    /**
     * Check if command still in sending queue.
     *
     * @param CommandInterface $command
     * @return bool
     */
    public function isQueued(CommandInterface $command): bool
    {
        return $this->queue->has($command->getID());
    }

    /**
     * @param CommandInterface $command
     */
    public function cancel(CommandInterface $command): void
    {
        // remove from queue
        $this->queue->pull($command->getID());
        $this->fetch($command->getID())->promise()->cancel();
    }

    /**
     * Reject pending promise.
     *
     * @param CommandInterface $command
     * @param \Throwable $reason
     */
    public function reject(CommandInterface $command, \Throwable $reason): void
    {
        $request = $this->fetch($command->getID());
        $request->reject($reason);
    }

    /**
     * @param int $id
     * @return Deferred
     */
    private function fetch(int $id): Deferred
    {
        $request = $this->get($id);

        try {
            return $request;
        } finally {
            unset($this->requests[$id]);
        }
    }

    /**
     * @param int $id
     * @return Deferred
     */
    private function get(int $id): Deferred
    {
        if (!isset($this->requests[$id])) {
            throw new \UnderflowException(\sprintf(self::ERROR_REQUEST_NOT_FOUND, $id));
        }

        return $this->requests[$id];
    }
}
