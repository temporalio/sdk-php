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

        $returnType = $operation['returnType'] === 'void' ? null : $operation['returnType'];

        return $this->callsInterceptor->with(
            fn(ExecuteNexusOperationInput $input): PromiseInterface => $this->ctx
                ->newUntypedNexusOperationStub($input->options)
                ->execute($input->operation, $input->args, $input->returnType),
            /** @see WorkflowOutboundCallsInterceptor::executeNexusOperation() */
            'executeNexusOperation',
        )(
            new ExecuteNexusOperationInput(
                $this->options->service,
                $operation['name'],
                $args,
                $this->options,
                $returnType,
            ),
        );
    }
}
