<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Router;

use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Runtime\WorkflowContext;
use Temporal\Client\Runtime\WorkflowContextInterface;
use Temporal\Client\Transport\Request\Request;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\Request\StartWorkflow as StartWorkflowRequest;
use Temporal\Client\Transport\TransportInterface;
use Temporal\Client\WorkerInterface;

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
    public function handle(RequestInterface $request)
    {
        if (! $workflow = $this->worker->findWorkflow($request->get('name'))) {
            throw new \RuntimeException(\sprintf(self::ERROR_INVALID_WORKFLOW, $request->get('name')));
        }

        try {
            return 'OK';
        } finally {
            $result = $this->execute($workflow, $request);

            $transport = $this->worker->getTransport();
            $transport->send(new Request('CompleteWorkflow', ['result' => $result]));
        }
    }

    /**
     * @param WorkflowInterface $workflow
     * @param StartWorkflowRequest $request
     * @return mixed
     * @throws \ReflectionException
     * @throws \Throwable
     */
    private function execute(WorkflowInterface $workflow, StartWorkflowRequest $request)
    {
        [$handler, $reflection] = [
            $workflow->getHandler(),
            $workflow->getReflectionHandler(),
        ];

        $context = new WorkflowContext($request);

        $additional = [
            WorkerInterface::class          => $this->worker,
            TransportInterface::class       => $this->worker->getTransport(),
            RequestInterface::class         => $request,
            WorkflowContext::class          => $context,
            WorkflowContextInterface::class => $context,
        ];

        $result = $this->app->runScope($additional, function () use ($reflection, $request) {
            $arguments = [
                'payload' => $payload = $request->get('payload')
            ];

            if (\is_array($payload)) {
                $arguments = \array_merge_recursive($arguments, $payload);
            }

            return $this->app->resolveArguments($reflection, $arguments);
        });

        return $handler(...$result);
    }
}
