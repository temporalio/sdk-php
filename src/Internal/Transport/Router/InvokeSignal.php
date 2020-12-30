<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\DataConverter\Payload;
use Temporal\Client\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Worker\LoopInterface;

final class InvokeSignal extends WorkflowProcessAwareRoute
{
    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @param RepositoryInterface $running
     * @param LoopInterface $loop
     */
    public function __construct(RepositoryInterface $running, LoopInterface $loop)
    {
        $this->loop = $loop;

        parent::__construct($running);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $instance = $this->findInstanceOrFail($payload['runId']);
        $handler = $instance->getSignalHandler($payload['name']);

        // todo: handle on protobuf level
        foreach ($payload['args'] as &$arg) {
            $arg = Payload::createRaw($arg['metadata'], $arg['data'] ?? null);
            unset($arg);
        }

        $executor = static fn() => $resolver->resolve($handler($payload['args'] ?? []));
        $this->loop->once(LoopInterface::ON_SIGNAL, $executor);
    }
}
