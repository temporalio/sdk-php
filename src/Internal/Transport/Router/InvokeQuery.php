<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use JetBrains\PhpStorm\Pure;
use React\Promise\Deferred;
use Temporal\DataConverter\Payload;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\LoopInterface;

final class InvokeQuery extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_QUERY_NOT_FOUND = 'unknown queryType %s. KnownQueryTypes=[%s]';

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @param RepositoryInterface $running
     * @param LoopInterface $loop
     */
    #[Pure]
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

        // todo: need data converter

        // TODO: handle on protobuf level
        // TODO: reduce side-effect (do not use references!!11)
        foreach ($payload['args'] as &$arg) {
            $arg = Payload::createRaw($arg['metadata'], $arg['data'] ?? null);
            unset($arg);
        }

        $instance = $this->findInstanceOrFail($runId);
        $handler = $this->findQueryHandlerOrFail($instance, $name);

        $executor = static function () use ($payload, $resolver, $handler) {
            $resolver->resolve($handler($payload['args'] ?? []));
        };

        $this->loop->once(LoopInterface::ON_QUERY, $executor);
    }

    /**
     * @param WorkflowInstanceInterface $instance
     * @param string $name
     * @return \Closure|null
     */
    private function findQueryHandlerOrFail(WorkflowInstanceInterface $instance, string $name): ?\Closure
    {
        $handler = $instance->findQueryHandler($name);

        if ($handler === null) {
            $available = \implode(' ', $instance->getQueryHandlerNames());

            throw new \LogicException(\sprintf(self::ERROR_QUERY_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
