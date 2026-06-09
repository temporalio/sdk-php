<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteNexusOperationInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowContextInterface;

final class NexusServiceProxy extends Proxy
{
    /** @var array<string, NexusOperationPrototype> Keyed by PHP method name. */
    private readonly array $operationsByMethod;

    /**
     * @param class-string $class
     * @param Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> $callsInterceptor
     */
    public function __construct(
        private string $class,
        NexusServicePrototype $prototype,
        private NexusOperationOptions $options,
        private WorkflowContextInterface $ctx,
        private Pipeline $callsInterceptor,
    ) {
        $byMethod = [];
        foreach ($prototype->getOperations() as $operation) {
            $byMethod[$operation->methodName] = $operation;
        }
        $this->operationsByMethod = $byMethod;
    }

    public function __call(string $method, array $args = []): PromiseInterface
    {
        $operation = $this->operationsByMethod[$method] ?? null;

        if ($operation === null) {
            throw new \BadMethodCallException(\sprintf(
                'Nexus service "%s" has no operation method "%s". '
                . 'Did you forget the #[Operation] attribute on the method?',
                $this->class,
                $method,
            ));
        }

        $service = $this->options->service;
        if ($service === '') {
            throw new \InvalidArgumentException(
                \sprintf('Nexus service name resolved to empty for stub class %s', $this->class),
            );
        }

        $returnType = $operation->outputType;

        return $this->callsInterceptor->with(
            fn(ExecuteNexusOperationInput $input): PromiseInterface => $this->ctx
                ->newUntypedNexusOperationStub($input->options)
                ->execute($input->operation, $input->args, $input->returnType, $input->nexusHeaders),
            /** @see WorkflowOutboundCallsInterceptor::executeNexusOperation() */
            'executeNexusOperation',
        )(
            new ExecuteNexusOperationInput(
                $this->options->endpoint,
                $service,
                $operation->name,
                $args,
                $this->options,
                $returnType,
            ),
        );
    }
}
