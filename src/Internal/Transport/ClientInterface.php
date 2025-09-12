<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use Internal\Destroy\Destroyable;
use React\Promise\PromiseInterface;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface;
use Temporal\Workflow\WorkflowContextInterface;

interface ClientInterface extends Destroyable
{
    /**
     * Send a request and return a promise.
     */
    public function request(RequestInterface $request, ?WorkflowContextInterface $context = null): PromiseInterface;

    /**
     * Sena a request without tracking the response.
     */
    public function send(CommandInterface $request): void;

    /**
     * Check if command still in sending queue.
     */
    public function isQueued(CommandInterface $command): bool;

    public function cancel(CommandInterface $command): void;

    /**
     * Reject pending promise.
     */
    public function reject(CommandInterface $command, \Throwable $reason): void;

    /**
     * Dispatch a response to the request.
     */
    public function dispatch(ServerResponseInterface $response): void;

    /**
     * Create a new client that can work with parent's request queue.
     */
    public function fork(): ClientInterface;
}
