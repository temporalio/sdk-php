<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteNexusOperationInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Nexus\OperationDefinition;
use Temporal\Nexus\ServiceDefinition;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowContextInterface;

final class NexusServiceProxy extends Proxy
{
    private const ERROR_UNDEFINED_OPERATION =
        'Nexus service "%s" has no operation method "%s". '
        . 'Did you forget the #[Operation] attribute on the method?';

    /** @var array<string, OperationDefinition> Keyed by PHP method name. */
    private readonly array $operationsByMethod;

    /**
     * @param class-string $class
     * @param Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> $callsInterceptor
     */
    public function __construct(
        private string $class,
        ServiceDefinition $service,
        private NexusOperationOptions $options,
        private WorkflowContextInterface $ctx,
        private Pipeline $callsInterceptor,
    ) {
        $byMethod = [];
        foreach ($service->operations as $operation) {
            if ($operation->methodName !== null) {
                $byMethod[$operation->methodName] = $operation;
            }
        }
        $this->operationsByMethod = $byMethod;
    }

    public function __call(string $method, array $args = []): PromiseInterface
    {
        $operation = $this->operationsByMethod[$method] ?? null;

        if ($operation === null) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_OPERATION, $this->class, $method),
            );
        }

        $service = $this->options->service;
        if ($service === '') {
            throw new \InvalidArgumentException(
                \sprintf('Nexus service name resolved to empty for stub class %s', $this->class),
            );
        }
        if ($operation->name === '') {
            throw new \InvalidArgumentException(
                \sprintf('Nexus operation name resolved to empty for %s::%s()', $this->class, $method),
            );
        }

        $returnType = $operation->outputType === 'void' ? null : $operation->outputType;

        return $this->callsInterceptor->with(
            fn(ExecuteNexusOperationInput $input): PromiseInterface => $this->ctx
                ->newUntypedNexusOperationStub($input->options)
                ->execute($input->operation, $input->args, $input->returnType, $input->nexusHeaders),
            /** @see WorkflowOutboundCallsInterceptor::executeNexusOperation() */
            'executeNexusOperation',
        )(
            new ExecuteNexusOperationInput(
                $service,
                $operation->name,
                $args,
                $this->options,
                $returnType,
            ),
        );
    }
}
