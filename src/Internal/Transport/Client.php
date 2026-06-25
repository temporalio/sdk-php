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
use Temporal\DataConverter\SerializationContext;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TemporalFailure;
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
     * @var array<int, array{Deferred, WorkflowContextInterface|null, SerializationContext|null}>
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

        [$deferred, $context, $serializationContext] = $this->requests[$id];
        unset($this->requests[$id]);

        $info = $context->getInfo();
        if ($info !== null && $response->getTickInfo()->historyLength > $info->historyLength) {
            $response->getTickInfo()->applyTo($info);
        }

        // Bind workflow context for promise resolution
        Workflow::setCurrentContext($context);
        if ($response instanceof FailureResponseInterface) {
            $failure = $response->getFailure();
            if ($serializationContext !== null && $failure instanceof TemporalFailure) {
                $failure->setSerializationContext($serializationContext);
            }

            $deferred->reject($failure);
        } else {
            $payloads = $response->getPayloads();
            if ($serializationContext !== null && $payloads !== null) {
                $payloads->setSerializationContext($serializationContext);
            }
            $deferred->resolve($payloads);
        }
    }

    public function request(RequestInterface $request, ?WorkflowContextInterface $context = null): PromiseInterface
    {
        $this->queue->push($request);

        $id = $request->getID();

        \array_key_exists($id, $this->requests) and throw new \OutOfBoundsException(
            \sprintf(self::ERROR_REQUEST_ID_DUPLICATION, $id),
        );

        $serializationContext = $request->getPayloads()?->getSerializationContext();

        $deferred = new Deferred();
        $this->requests[$id] = [$deferred, $context, $serializationContext];

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
