<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\LoopInterface;

final class InvokeSignal extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_SIGNAL_NOT_FOUND = 'unknown signalType %s. KnownSignalTypes=[%s]';

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
        ['runId' => $runId, 'name' => $name] = $payload;

        $instance = $this->findInstanceOrFail($runId);
        $handler = $this->findSignalHandlerOrFail($instance, $name);

        $executor = static fn() => $resolver->resolve($handler($payload['args'] ?? []));
        $this->loop->once(LoopInterface::ON_SIGNAL, $executor);
    }

    /**
     * @param WorkflowInstanceInterface $instance
     * @param string $name
     * @return \Closure|null
     */
    private function findSignalHandlerOrFail(WorkflowInstanceInterface $instance, string $name): ?\Closure
    {
        $handler = $instance->findSignalHandler($name);

        if ($handler === null) {
            $available = \implode(' ', $instance->getSignalHandlerNames());

            throw new \LogicException(\sprintf(self::ERROR_SIGNAL_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
