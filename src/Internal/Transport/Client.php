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
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\Transport\Request\UndefinedResponse;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContextInterface;

/**
 * @internal Client is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Internal\Transport
 */
final class Client implements ClientInterface
{
    private const ERROR_REQUEST_ID_DUPLICATION =
        'Unable to create a new request because a ' .
        'request with id %d has already been sent';
    private const ERROR_REQUEST_NOT_FOUND =
        'Unable to receive a request with id %d because ' .
        'a request with that identifier was not sent';

    /**
     * @var array<int, array{Deferred, WorkflowContextInterface|null}>
     */
    private array $requests = [];

    public function __construct(
        private readonly QueueInterface $queue,
    ) {}

    public function dispatch(ServerResponseInterface $response): void
    {
        $id = $response->getID();
        if (!isset($this->requests[$id])) {
            $this->send(new UndefinedResponse(
                \sprintf('Got the response to undefined request %s', $id),
            ));
            return;
        }

        [$deferred, $context] = $this->requests[$id];
        unset($this->requests[$id]);

        $info = $context->getInfo();
        if ($info !== null && $response->getTickInfo()->historyLength > $info->historyLength) {
            $tickInfo = $response->getTickInfo();
            /** @psalm-suppress InaccessibleProperty */
            $info->historyLength = $tickInfo->historyLength;
            /** @psalm-suppress InaccessibleProperty */
            $info->historySize = $tickInfo->historySize;
            /** @psalm-suppress InaccessibleProperty */
            $info->shouldContinueAsNew = $tickInfo->continueAsNewSuggested;
        }

        // Bind workflow context for promise resolution
        Workflow::setCurrentContext($context);
        if ($response instanceof FailureResponseInterface) {
            $deferred->reject($response->getFailure());
        } else {
            $deferred->resolve($response->getPayloads());
        }
    }

    public function request(RequestInterface $request, ?WorkflowContextInterface $context = null): PromiseInterface
    {
        $this->queue->push($request);

        $id = $request->getID();

        \array_key_exists($id, $this->requests) and throw new \OutOfBoundsException(
            \sprintf(self::ERROR_REQUEST_ID_DUPLICATION, $id),
        );

        $deferred = new Deferred();
        $this->requests[$id] = [$deferred, $context];

        return $deferred->promise();
    }

    public function send(CommandInterface $request): void
    {
        $this->queue->push($request);
    }

    public function isQueued(CommandInterface $command): bool
    {
        return $this->queue->has($command->getID());
    }

    public function cancel(CommandInterface $command): void
    {
        // remove from queue
        $this->queue->pull($command->getID());
        $this->reject($command, new CanceledFailure('internal cancel'));
    }

    public function reject(CommandInterface $command, \Throwable $reason): void
    {
        $this->fetch($command->getID())->reject($reason);
    }

    public function fork(): ClientInterface
    {
        return new DetachedClient($this, function (array $ids): void {
            foreach ($ids as $id) {
                unset($this->requests[$id]);
            }
        });
    }

    public function destroy(): void
    {
        $this->requests = [];
        unset($this->queue);
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

        return $this->requests[$id][0];
    }
}
