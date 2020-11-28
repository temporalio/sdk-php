<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Exception\CancellationException;
use Temporal\Client\Internal\Queue\QueueInterface;
use Temporal\Client\Worker\Command\ErrorResponseInterface;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\Command\ResponseInterface;
use Temporal\Client\Worker\Command\SuccessResponseInterface;

/**
 * @internal Client is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Transport
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
     * @param QueueInterface $queue
     */
    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @psalm-param SuccessResponseInterface|ErrorResponseInterface $response
     * @param ResponseInterface $response
     */
    public function dispatch(ResponseInterface $response): void
    {
        $deferred = $this->fetch($response->getId());

        if ($response instanceof ErrorResponseInterface) {
            $deferred->reject($response->toException());
        } else {
            $result = $response->getResult();

            $deferred->resolve(\current($result) ?: false);
        }
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    private function promise(RequestInterface $request): PromiseInterface
    {
        $id = $request->getId();

        if (isset($this->requests[$id])) {
            throw new \LogicException(\sprintf(self::ERROR_REQUEST_ID_DUPLICATION, $id));
        }

        $this->requests[$id] = $deferred = new Deferred(function () use ($id) {
            throw new CancellationException("Request with id ${id} was canceled");
        });

        return $deferred->promise();
    }

    /**
     * @param int $id
     * @return Deferred
     */
    private function fetch(int $id): Deferred
    {
        if (! isset($this->requests[$id])) {
            throw new \LogicException(\sprintf(self::ERROR_REQUEST_NOT_FOUND, $id));
        }

        try {
            return $this->requests[$id];
        } finally {
            unset($this->requests[$id]);
        }
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        $this->queue->push($request);

        return $this->promise($request);
    }

    /**
     * @param int $id
     */
    public function cancel(int $id): void
    {
        $exception = new CancellationException("Request with id ${id} was canceled");

        $deferred = $this->fetch($id);
        $deferred->reject($exception);
    }
}
