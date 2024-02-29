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
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Worker\Transport\Command\FailureResponse;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;
use Temporal\Worker\Transport\Command\UpdateResponse;
use Temporal\Workflow\Update\UpdateResult;

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
     * @param QueueInterface $queue
     * @param callable $onMessage
     */
    public function __construct(QueueInterface $queue, callable $onMessage)
    {
        $this->queue = $queue;

        $this->onMessage($onMessage);
    }

    /**
     * {@inheritDoc}
     */
    public function onMessage(callable $then): void
    {
        $this->onMessage = $then(...);
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     */
    public function dispatch(ServerRequestInterface $request, array $headers): void
    {
        try {
            $result = ($this->onMessage)($request, $headers);
        } catch (\Throwable $e) {
            $this->queue->push(new FailureResponse($e, $request->getID()));

            return;
        }

        if (!$result instanceof PromiseInterface) {
            $error = \sprintf(self::ERROR_INVALID_RETURN_TYPE, PromiseInterface::class, \get_debug_type($result));
            throw new \BadMethodCallException($error);
        }

        $result->then($this->onFulfilled($request), $this->onRejected($request));
    }

    /**
     * @param RequestInterface $request
     * @return \Closure
     */
    private function onFulfilled(ServerRequestInterface $request): \Closure
    {
        return function ($result) use ($request) {
            if ($result::class === UpdateResult::class) {
                $response = new UpdateResponse(
                    command: $result->command,
                    values: $result->result,
                    failure: $result->failure,
                    updateId: $request->getOptions()['updateId'] ?? null,
                );
            } else {
                $response = new SuccessResponse($result, $request->getID());
            }

            $this->queue->push($response);

            return $response;
        };
    }

    /**
     * @param ServerRequestInterface $request
     * @return \Closure
     */
    private function onRejected(ServerRequestInterface $request): \Closure
    {
        return function ($result) use ($request) {
            if (!$result instanceof \Throwable) {
                $result = new \InvalidArgumentException(
                    \sprintf(self::ERROR_INVALID_REJECTION_TYPE, \get_debug_type($result))
                );
            }

            $response = new FailureResponse($result, $request->getID());
            $this->queue->push($response);

            return $response;
        };
    }
}
