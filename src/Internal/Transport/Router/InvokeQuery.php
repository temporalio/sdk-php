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
use Temporal\Api\Sdk\V1\WorkflowDefinition;
use Temporal\Api\Sdk\V1\WorkflowMetadata;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Internal\Declaration\EntityNameValidator;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class InvokeQuery extends WorkflowProcessAwareRoute
{
    private const ERROR_QUERY_NOT_FOUND = 'unknown queryType %s. KnownQueryTypes=[%s]';

    #[Pure]
    public function __construct(
        RepositoryInterface $running,
        private readonly LoopInterface $loop,
    ) {
        parent::__construct($running);
    }

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $name = $request->getOptions()['name'] ?? '';
        $process = $this->findProcessOrFail($request->getID());
        $context = $process->getContext();

        \assert(\is_string($name) && $name !== '');

        match ($name) {
            '__temporal_workflow_metadata' => $this->workflowMetadata($resolver, $context),
            EntityNameValidator::QUERY_TYPE_STACK_TRACE => $this->stackTrace($resolver, $context),
            EntityNameValidator::ENHANCED_QUERY_TYPE_STACK_TRACE => $this->enhancedStackTrace($resolver, $context),
            default => $this->handleQuery($name, $request, $resolver, $context, $headers),
        };
    }

    /**
     * Handles the query request by finding the appropriate query handler and executing it.
     *
     * @param non-empty-string $name
     */
    private function handleQuery(
        string $name,
        ServerRequestInterface $request,
        Deferred $resolver,
        WorkflowContext $context,
        array $headers,
    ): void {
        if (\str_starts_with($name, EntityNameValidator::COMMON_BUILTIN_PREFIX)) {
            $resolver->reject(new \LogicException("The Query method `{$name}` is not supported by the SDK."));
            return;
        }

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

    /**
     * Returns workflow metadata including query, signal, and update definitions.
     */
    private function workflowMetadata(Deferred $resolver, WorkflowContext $context): void
    {
        $this->loop->once(
            LoopInterface::ON_QUERY,
            static function () use ($resolver, $context): void {
                try {
                    $result = EncodedValues::fromValues([
                        (new WorkflowMetadata())->setDefinition(
                            (new WorkflowDefinition())
                                ->setQueryDefinitions($context->getQueryDispatcher()->getQueryHandlers())
                                ->setSignalDefinitions($context->getSignalDispatcher()->getSignalHandlers())
                                ->setUpdateDefinitions($context->getUpdateDispatcher()->getUpdateHandlers()),
                        ),
                    ]);

                    $resolver->resolve($result);
                } catch (\Throwable $e) {
                    $resolver->reject($e);
                }
            },
        );
    }

    private function stackTrace(Deferred $resolver, WorkflowContext $context): void
    {
        $this->loop->once(
            LoopInterface::ON_QUERY,
            static function () use ($resolver, $context): void {
                try {
                    $result = EncodedValues::fromValues([$context->getStackTrace()]);

                    $resolver->resolve($result);
                } catch (\Throwable $e) {
                    $resolver->reject($e);
                }
            },
        );
    }

    private function enhancedStackTrace(Deferred $resolver, WorkflowContext $context): void
    {
        $this->loop->once(
            LoopInterface::ON_QUERY,
            static function () use ($resolver, $context): void {
                try {
                    $result = EncodedValues::fromValues([
                        $context->getEnhancedStackTrace(),
                    ]);

                    $resolver->resolve($result);
                } catch (\Throwable $e) {
                    $resolver->reject($e);
                }
            },
        );
    }
}
