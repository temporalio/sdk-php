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
use Temporal\Client\Protocol\Command\ErrorResponseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Protocol\Command\SuccessResponse;
use Temporal\Client\Protocol\Command\SuccessResponseInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;
use Temporal\Client\Protocol\WorkflowProtocol\Context;
use Temporal\Client\Protocol\WorkflowProtocol\Decoder;
use Temporal\Client\Protocol\WorkflowProtocol\Encoder;

/**
 * @internal WorkflowProtocol is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Protocol
 */
final class WorkflowProtocol implements WorkflowProtocolInterface
{
    /**
     * @var \DateTimeZone
     */
    private \DateTimeZone $zone;

    /**
     * @var QueueInterface
     */
    private QueueInterface $queue;

    /**
     * @var Context
     */
    private Context $context;

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

        $this->zone = new \DateTimeZone('UTC');
        $this->queue = new SplQueue();
        $this->context = new Context($this->zone);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        return $this->context->promiseForRequest(
            $this->sendDefer($request)
        );
    }

    /**
     * @param CommandInterface|RequestInterface|ResponseInterface $cmd
     * @return CommandInterface|RequestInterface|ResponseInterface
     */
    private function sendDefer(CommandInterface $cmd): CommandInterface
    {
        $this->queue->push($cmd);

        return $cmd;
    }

    /**
     * @param string $request
     * @return string
     * @throws \JsonException
     * @throws \Exception
     */
    public function next(string $request): string
    {
        $this->parse($request);

        return Encoder::encode($this->now(), $this->queue, $this->context->runId);
    }

    /**
     * @param string $request
     * @throws \JsonException
     */
    private function parse(string $request): void
    {
        ['rid' => $rid, 'tickTime' => $tick, 'commands' => $commands] = Decoder::decode($request, $this->zone);

        $this->context->update($rid, $tick);

        foreach ($commands as $command) {
            if ($command instanceof RequestInterface) {
                $this->dispatchRequest($command);
            } else {
                $this->dispatchResponse($command);
            }
        }
    }

    /**
     * @param RequestInterface $request
     */
    private function dispatchRequest(RequestInterface $request): void
    {
        $deferred = new Deferred();

        $fulfilled = function ($result) use ($request): void {
            $this->sendDefer(new SuccessResponse($result, $request->getId()));
        };

        $rejected = function (\Throwable $e) use ($request): void {
            $this->sendDefer(ErrorResponse::fromException($e, $request->getId()));
        };

        $deferred->promise()->then($fulfilled, $rejected);

        try {
            ($this->onRequest)($request, $deferred);
        } catch (\Throwable $e) {
            $deferred->reject($e);
        }
    }

    /**
     * @param ErrorResponseInterface|SuccessResponseInterface|ResponseInterface $response
     */
    private function dispatchResponse(ResponseInterface $response): void
    {
        $deferred = $this->context->fetch($response->getId());

        if ($response instanceof ErrorResponseInterface) {
            $deferred->reject($response->toException());
        } else {
            $deferred->resolve($response->getResult());
        }
    }

    /**
     * @return \DateTimeInterface
     * @throws \Exception
     */
    private function now(): \DateTimeInterface
    {
        return new \DateTime('now', $this->zone);
    }
}
