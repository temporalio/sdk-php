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
use Temporal\Client\Protocol\Command\CommandInterface;
use Temporal\Client\Protocol\Command\ErrorResponse;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Protocol\Command\SuccessResponse;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;

final class Protocol implements ProtocolInterface
{
    /**
     * @var QueueInterface
     */
    private QueueInterface $responses;

    /**
     * @var \Closure
     */
    private \Closure $onRequest;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @param \Closure $onRequestHandler
     * @throws \Exception
     */
    public function __construct(\Closure $onRequestHandler)
    {
        $this->onRequest = $onRequestHandler;
        $this->responses = new SplQueue();
        $this->client = new Client($this->responses);
    }

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        return $this->client->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(int $id): void
    {
        $this->client->cancel($id);
    }

    /**
     * @param string $request
     * @param array $headers
     * @return string
     * @throws \JsonException
     */
    public function next(string $request, array $headers): string
    {
        $commands = Decoder::decode($request);

        foreach ($commands as $command) {
            if ($command instanceof RequestInterface) {
                $this->onRequest($command, $headers);
            } else {
                $this->client->dispatch($command);
            }
        }

        return Encoder::encode($this->responses);
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     */
    private function onRequest(RequestInterface $request, array $headers): void
    {
        try {
            $then = function ($payload) use ($request) {
                return $this->sendDefer(new SuccessResponse($payload, $request->getId()));
            };

            $otherwise = function (\Throwable $e) use ($request): void {
                $this->sendDefer(ErrorResponse::fromException($e, $request->getId()));
            };

            $promise = ($this->onRequest)($request, $headers);
            $promise->then($then, $otherwise);
        } catch (\Throwable $e) {
            $this->sendDefer(ErrorResponse::fromException($e, $request->getId()));
        }
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
}
