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
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface;
use Temporal\Workflow\WorkflowContextInterface;

/**
 * @internal Client is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Internal\Transport
 */
final class DetachedClient implements ClientInterface
{
    /** @var list<int> */
    private array $requests = [];

    /**
     * @param \Closure(list<int>): void $cleanup Handler that removes requests from the parent using their IDs.
     */
    public function __construct(
        private ClientInterface $parent,
        private \Closure $cleanup,
    ) {}

    #[\Override]
    public function request(RequestInterface $request, ?WorkflowContextInterface $context = null): PromiseInterface
    {
        $this->requests[] = $request->getID();
        return $this->parent->request($request, $context);
    }

    #[\Override]
    public function send(CommandInterface $request): void
    {
        $this->parent->send($request);
    }

    #[\Override]
    public function isQueued(CommandInterface $command): bool
    {
        return $this->parent->isQueued($command);
    }

    #[\Override]
    public function cancel(CommandInterface $command): void
    {
        $this->parent->cancel($command);
    }

    #[\Override]
    public function reject(CommandInterface $command, \Throwable $reason): void
    {
        $this->parent->reject($command, $reason);
    }

    #[\Override]
    public function dispatch(ServerResponseInterface $response): void
    {
        $this->parent->dispatch($response);
    }

    #[\Override]
    public function fork(): ClientInterface
    {
        return $this->parent->fork();
    }

    public function destroy(): void
    {
        $this->requests === [] or ($this->cleanup)($this->requests);
        $this->requests = [];
        unset($this->parent, $this->cleanup);
    }
}
