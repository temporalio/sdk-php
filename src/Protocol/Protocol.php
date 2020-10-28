<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Exception\CancellationException;
use Temporal\Client\Protocol\Command\CommandInterface;
use Temporal\Client\Protocol\Command\ErrorResponse;
use Temporal\Client\Protocol\Command\ErrorResponseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Protocol\Command\SuccessResponseInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;

final class Protocol implements ProtocolInterface
{
    /**
     * @var QueueInterface
     */
    private QueueInterface $responses;

    /**
     * @var array|Deferred[]
     */
    private array $requests = [];

    /**
     * @var \Closure
     */
    private \Closure $onRequest;

    /**
     * @param \Closure $onRequest
     * @throws \Exception
     */
    public function __construct(\Closure $onRequest)
    {
        $this->onRequest = $onRequest;
        $this->responses = new SplQueue();
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        return $this->promiseForRequest(
            $this->sendDefer($request)
        );
    }

    /**
     * @param int $id
     * @return bool
     */
    public function cancel(int $id): bool
    {
        try {
            $deferred = $this->fetchRequestDeferred($id);
            $deferred->reject(new CancellationException('Cancel'));

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function promiseForRequest(RequestInterface $request): PromiseInterface
    {
        $id = $request->getId();

        $this->requests[$id] = $deferred = new Deferred();

        return $deferred->promise();
    }

    /**
     * @param CommandInterface|RequestInterface|ResponseInterface $cmd
     * @return CommandInterface|RequestInterface|ResponseInterface
     */
    private function sendDefer(CommandInterface $cmd): CommandInterface
    {
        $this->responses->push($cmd);

        return $cmd;
    }

    /**
     * @param string $request
     * @param array $headers
     * @return string
     * @throws \JsonException
     */
    public function next(string $request, array $headers): string
    {
        $this->parse($request, $headers);

        return Encoder::encode($this->responses);
    }

    /**
     * @param string $request
     * @param array $headers
     * @throws \JsonException
     */
    private function parse(string $request, array $headers): void
    {
        $commands = Decoder::decode($request);

        foreach ($commands as $command) {
            if ($command instanceof RequestInterface) {
                $this->dispatchRequest($command, $headers);
            } else {
                $this->dispatchResponse($command);
            }
        }
    }



    /**
     * @param RequestInterface $request
     * @param array $headers
     */
    private function dispatchRequest(RequestInterface $request, array $headers): void
    {
        try {
            $this->sendDefer(($this->onRequest)($request, $headers));
        } catch (\Throwable $e) {
            $this->sendDefer(ErrorResponse::fromException($e, $request->getId()));
        }
    }

    /**
     * @param ResponseInterface $response
     */
    private function dispatchResponse(ResponseInterface $response): void
    {
        $this->answer($response);
    }

    /**
     * @param ErrorResponseInterface|SuccessResponseInterface|ResponseInterface $response
     */
    public function answer(ResponseInterface $response): void
    {
        $deferred = $this->fetchRequestDeferred($response->getId());

        if ($response instanceof ErrorResponseInterface) {
            $deferred->reject($response->toException());
        } else {
            $deferred->resolve($response->getResult());
        }
    }

    /**
     * @param int $id
     * @return Deferred
     */
    private function fetchRequestDeferred(int $id): Deferred
    {
        $deferred = $this->requests[$id] ?? null;

        if ($deferred === null) {
            throw new \OutOfBoundsException('The received response does not match any existing request');
        }

        unset($this->requests[$id]);

        return $deferred;
    }
}
