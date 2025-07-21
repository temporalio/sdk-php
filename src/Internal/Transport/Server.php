<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use React\Promise\PromiseInterface;
use Temporal\Internal\Exception\UndefinedRequestException;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Transport\Request\UndefinedResponse;
use Temporal\Worker\Transport\Command\Client\FailedClientResponse;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

/**
 * @psalm-import-type OnMessageHandler from ServerInterface
 */
final class Server implements ServerInterface
{
    private const ERROR_INVALID_RETURN_TYPE = 'Request handler must return an instance of \%s, but returned %s';
    private const ERROR_INVALID_REJECTION_TYPE =
        'An internal error has occurred: ' .
        'Promise rejection must contain an instance of \Throwable, however %s is given';

    private \Closure $onMessage;
    private QueueInterface $queue;

    /**
     * @psalm-param OnMessageHandler $onMessage
     */
    public function __construct(QueueInterface $queue, callable $onMessage)
    {
        $this->queue = $queue;

        $this->onMessage($onMessage);
    }

    public function onMessage(callable $then): void
    {
        $this->onMessage = $then(...);
    }

    /**
     * @param RequestInterface $request
     */
    public function dispatch(ServerRequestInterface $request, array $headers): void
    {
        try {
            $result = ($this->onMessage)($request, $headers);
        } catch (\Throwable $e) {
            $this->queue->push(new FailedClientResponse($request->getID(), $e));

            return;
        }

        $result instanceof PromiseInterface or throw new \BadMethodCallException(\sprintf(
            self::ERROR_INVALID_RETURN_TYPE,
            PromiseInterface::class,
            \get_debug_type($result),
        ));

        $result->then($this->onFulfilled($request), $this->onRejected($request));
    }

    /**
     * @return \Closure(mixed): SuccessResponseInterface
     */
    private function onFulfilled(ServerRequestInterface $request): \Closure
    {
        return function ($result) use ($request) {
            $response = new SuccessClientResponse($request->getID(), $result);
            $this->queue->push($response);

            return $response;
        };
    }

    /**
     * @return \Closure(\Throwable): (FailureResponseInterface|never)
     */
    private function onRejected(ServerRequestInterface $request): \Closure
    {
        return function (\Throwable $e) use ($request) {
            if ($e::class === UndefinedRequestException::class) {
                // This is not a FailureResponseInterface, but it's a better place to handle it.
                $response = new UndefinedResponse($e->getMessage());
                $this->queue->push($response);
                throw $e;
            }

            $response = new FailedClientResponse($request->getID(), $e);
            $this->queue->push($response);

            return $response;
        };
    }
}
