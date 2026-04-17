<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteNexusOperationInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowContextInterface;

final class NexusServiceProxy extends Proxy
{
    private const ERROR_UNDEFINED_OPERATION =
        'Nexus service "%s" has no operation method "%s". '
        . 'Did you forget the #[Operation] attribute on the method?';

    /**
     * @param class-string $class
     * @param array<string, array{name: string, method: string, returnType: string}> $operations
     * @param Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> $callsInterceptor
     */
    public function __construct(
        private string $class,
        private array $operations,
        private NexusOperationOptions $options,
        private WorkflowContextInterface $ctx,
        private Pipeline $callsInterceptor,
    ) {}

    public function __call(string $method, array $args = []): PromiseInterface
    {
        $operation = $this->operations[$method] ?? null;

        if ($operation === null) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_OPERATION, $this->class, $method),
            );
        }

        $service = $this->options->service;
        $opName = $operation['name'];
        // `newNexusServiceStub()` fills `service` from the #[Service] attribute
        // when the caller leaves it blank; if both are missing we land here
        // with '' and must fail fast rather than ship an empty wire value.
        if ($service === '') {
            throw new \InvalidArgumentException(
                \sprintf('Nexus service name resolved to empty for stub class %s', $this->class),
            );
        }
        if ($opName === '') {
            throw new \InvalidArgumentException(
                \sprintf('Nexus operation name resolved to empty for %s::%s()', $this->class, $method),
            );
        }

        $returnType = $operation['returnType'] === 'void' ? null : $operation['returnType'];

        return $this->callsInterceptor->with(
            fn(ExecuteNexusOperationInput $input): PromiseInterface => $this->ctx
                ->newUntypedNexusOperationStub($input->options)
                ->execute($input->operation, $input->args, $input->returnType),
            /** @see WorkflowOutboundCallsInterceptor::executeNexusOperation() */
            'executeNexusOperation',
        )(
            new ExecuteNexusOperationInput(
                $service,
                $opName,
                $args,
                $this->options,
                $returnType,
            ),
        );
    }
}
