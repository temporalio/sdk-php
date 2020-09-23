<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Router;

use React\Promise\PromiseInterface;
use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Runtime\WorkflowContext;
use Temporal\Client\Runtime\WorkflowContextInterface;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\Request\StartWorkflow as StartWorkflowRequest;

class StartWorkflow extends Route
{
    /**
     * @var string
     */
    private const ERROR_INVALID_WORKFLOW = 'Workflow named "%s" not registered';

    /**
     * @return string
     */
    public function getRequest(): string
    {
        return StartWorkflowRequest::class;
    }

    /**
     * @param StartWorkflowRequest|RequestInterface $request
     * @return mixed
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function handle(RequestInterface $request): void
    {
        if (! $workflow = $this->worker->findWorkflow($request->get('name'))) {
            throw new \RuntimeException(\sprintf(self::ERROR_INVALID_WORKFLOW, $request->get('name')));
        }

        $this->execute($workflow, $request);
    }

    /**
     * TODO It shouldn't be in the router
     *
     * @param WorkflowInterface $workflow
     * @param StartWorkflowRequest $request
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    private function execute(WorkflowInterface $workflow, StartWorkflowRequest $request): void
    {
        //
        // Create new workflow context
        //
        $reflection = $workflow->getReflectionHandler();
        $context = new WorkflowContext($request, $this->worker->getTransport());

        //
        // Collect execution arguments
        //
        $additional = [
            WorkflowContext::class          => $context,
            WorkflowContextInterface::class => $context,
        ];

        $arguments = $this->app->runScope($additional, function () use ($reflection, $request) {
            $arguments = [
                'payload' => $payload = $request->get('payload')
            ];

            if (\is_array($payload)) {
                $arguments = \array_merge_recursive($arguments, $payload);
            }

            return $this->app->resolveArguments($reflection, $arguments);
        });

        //
        // Execute
        //
        $handler = $workflow->getHandler();
        $result = $handler(...$arguments);

        if (! $result instanceof \Generator) {
            return;
        }

        $this->process($context, $result);
    }

    /**
     * @param WorkflowContextInterface $context
     * @param \Generator $stream
     */
    private function process(WorkflowContextInterface $context, \Generator $stream): void
    {
        if ($stream->valid()) {
            $promise = $stream->current();

            //
            assert($promise instanceof PromiseInterface);

            $promise->then(function ($result) use ($context, $stream) {
                $stream->send($result);

                if (! $stream->valid()) {
                    $context->complete($stream->getReturn());
                    return;
                }

                $this->process($context, $stream);
            });
        }
    }
}
