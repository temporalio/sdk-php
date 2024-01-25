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
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\Update\UpdateResult;

final class InvokeUpdate extends WorkflowProcessAwareRoute
{
    private const ERROR_HANDLER_NOT_FOUND = 'unknown updateName %s. KnownUpdateNames=[%s]';

    #[Pure]
    public function __construct(
        RepositoryInterface $running,
        private LoopInterface $loop,
    ) {
        parent::__construct($running);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $type = $request->getOptions()['type'] ?? null;
        if ($type === 'validate') {
            $resolver->resolve(new UpdateResult(
                status: 'InvokeUpdate',
                options: \array_intersect_key($request->getOptions(), ['updateId' => true])
            ));

            return;
        }

        $name = $request->getOptions()['name'];
        $process = $this->findProcessOrFail($request->getID());
        $context = $process->getContext();
        $instance = $process->getWorkflowInstance();
        $handler = $this->findQueryHandlerOrFail($instance, $name);

        $this->loop->once(
            LoopInterface::ON_SIGNAL,
            static function () use ($name, $request, $resolver, $handler, $context): void {
                try {
                    // Define Context for interceptors Pipeline
                    Workflow::setCurrentContext($context);

                    /** @psalm-suppress InaccessibleProperty */
                    $context->getInfo()->historyLength = $request->getHistoryLength();

                    // todo Header from request?
                    $result = $handler(new UpdateInput(
                        signalName: $name,
                        info: $context->getInfo(),
                        arguments: $request->getPayloads(),
                        header: $context->getHeader()),
                    );
                    $resolver->resolve(new UpdateResult(
                        status: 'InvokeUpdate',
                        result: $result,
                        options: \array_intersect_key($request->getOptions(), ['updateId' => true])
                    ));
                } catch (\Throwable $e) {
                    $resolver->reject($e);
                }
            },
        );
    }

    /**
     * @param WorkflowInstanceInterface $instance
     * @param string $name
     * @return \Closure|null
     */
    private function findQueryHandlerOrFail(WorkflowInstanceInterface $instance, string $name): ?\Closure
    {
        $handler = $instance->findUpdateHandler($name);

        if ($handler === null) {
            $available = \implode(' ', $instance->getUpdateHandlerNames());

            throw new \LogicException(\sprintf(self::ERROR_HANDLER_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
