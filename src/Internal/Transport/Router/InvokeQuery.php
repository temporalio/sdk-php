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
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class InvokeQuery extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_QUERY_NOT_FOUND = 'unknown queryType %s. KnownQueryTypes=[%s]';

    private LoopInterface $loop;

    #[Pure]
    public function __construct(
        RepositoryInterface $running,
        LoopInterface $loop,
    ) {
        $this->loop = $loop;

        parent::__construct($running);
    }

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        /** @var non-empty-string $name */
        $name = $request->getOptions()['name'];
        $process = $this->findProcessOrFail($request->getID());
        $context = $process->getContext();
        $handler = $this->findQueryHandlerOrFail($context->getQueryDispatcher(), $name);

        $this->loop->once(
            LoopInterface::ON_QUERY,
            static function () use ($name, $request, $resolver, $handler, $context, $headers): void {
                try {
                    // Define Context for interceptors Pipeline
                    Workflow::setCurrentContext($context);

                    $info = $context->getInfo();
                    $tickInfo = $request->getTickInfo();
                    /** @psalm-suppress InaccessibleProperty */
                    $info->historyLength = $tickInfo->historyLength;
                    /** @psalm-suppress InaccessibleProperty */
                    $info->historySize = $tickInfo->historySize;
                    /** @psalm-suppress InaccessibleProperty */
                    $info->shouldContinueAsNew = $tickInfo->continueAsNewSuggested;

                    $result = $handler(new QueryInput($name, $request->getPayloads(), $info));
                    $resolver->resolve(EncodedValues::fromValues([$result]));
                } catch (\Throwable $e) {
                    $resolver->reject($e);
                }
            },
        );
    }

    /**
     * @param non-empty-string $name
     * @return \Closure(QueryInput): mixed
     */
    private function findQueryHandlerOrFail(QueryDispatcher $dispatcher, string $name): \Closure
    {
        return $dispatcher->findQueryHandler($name) ?? throw new \LogicException(
            \sprintf(
                self::ERROR_QUERY_NOT_FOUND,
                $name,
                \implode(' ', $dispatcher->getQueryHandlerNames()),
            ),
        );
    }
}
