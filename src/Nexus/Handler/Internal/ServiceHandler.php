<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Api\Common\V1\Payloads;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Interceptor\NexusOperationOutboundCallsInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\NexusServiceInstance;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Handler\SyncOperationStartResult;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;

/**
 * @internal
 */
final class ServiceHandler implements HandlerInterface
{
    /**
     * @param array<string, NexusServiceInstance> $instances
     */
    private function __construct(
        private readonly array $instances,
        private readonly DataConverterInterface $dataConverter,
        private readonly PipelineProvider $interceptorProvider = new SimplePipelineProvider(),
    ) {}

    /**
     * @param NexusServiceInstance[] $instances
     */
    public static function create(
        DataConverterInterface $dataConverter,
        array $instances,
        PipelineProvider $interceptorProvider = new SimplePipelineProvider(),
    ): self {
        if (\count($instances) === 0) {
            throw new InvalidArgumentException('No service instances defined');
        }

        $instancesByName = [];
        foreach ($instances as $instance) {
            $name = $instance->prototype->getID();
            if (isset($instancesByName[$name])) {
                throw new InvalidArgumentException(
                    "Multiple instances registered for service name '{$name}'",
                );
            }
            $instancesByName[$name] = $instance;
        }

        return new self($instancesByName, $dataConverter, $interceptorProvider);
    }

    public function startOperation(
        OperationContext $context,
        OperationStartDetails $details,
        ValuesInterface $input,
        ?WorkflowClientInterface $workflowClient,
        NexusOperationContext $operationContext,
    ): OperationStartResult {
        [$instance, $handler] = $this->resolveHandler($context);

        $operations = $instance->prototype->getOperations();
        $definition = $operations[$context->operation];

        $result = $this->dispatch(
            new NexusContext(
                operation: self::publicOperationContext($operationContext),
                workflowClient: $workflowClient,
                current: $context,
                startDetails: $details,
                outboundPipeline: $this->interceptorProvider
                    ->getPipeline(NexusOperationOutboundCallsInterceptor::class),
            ),
            static fn(StartOperationInput $input): OperationStartResult => $handler->start(
                $input->operationContext,
                $input->startDetails,
                $input->input instanceof ValuesInterface
                    ? self::decodeInput($input->input, $input->operationContext, $definition->inputType)
                    : $input->input,
            ),
            'startOperation',
            new StartOperationInput($context, $details, $input),
        );

        \assert($result instanceof OperationStartResult);

        if (!$result instanceof SyncOperationStartResult) {
            return $result;
        }

        return OperationStartResult::sync(
            $this->encodeResult(
                $result->value,
                $context,
                $definition->outputType,
            ),
        );
    }

    public function cancelOperation(
        OperationContext $context,
        OperationCancelDetails $details,
        ?WorkflowClientInterface $workflowClient,
        NexusOperationContext $operationContext,
    ): void {
        [$instance, $handler] = $this->resolveHandler($context);

        $definition = $instance->prototype->getOperations()[$context->operation];
        if (!$definition->async) {
            throw HandlerException::create(
                ErrorType::NotImplemented,
                \sprintf(
                    'Operation %s/%s is synchronous and cannot be cancelled',
                    $context->service,
                    $context->operation,
                ),
            );
        }

        $this->dispatch(
            new NexusContext(
                operation: self::publicOperationContext($operationContext),
                workflowClient: $workflowClient,
                current: $context,
                cancelDetails: $details,
                outboundPipeline: $this->interceptorProvider
                    ->getPipeline(NexusOperationOutboundCallsInterceptor::class),
            ),
            static function (CancelOperationInput $input) use ($handler): void {
                $handler->cancel($input->operationContext, $input->cancelDetails);
            },
            'cancelOperation',
            new CancelOperationInput($context, $details),
        );
    }

    private static function publicOperationContext(NexusOperationContext $operationContext): ?NexusOperationContext
    {
        if ($operationContext->namespace === '' || $operationContext->taskQueue === '') {
            return null;
        }
        return $operationContext;
    }

    private static function decodeInput(
        ValuesInterface $input,
        OperationContext $context,
        Type $inputType,
    ): mixed {
        try {
            return $inputType->getName() === Type::TYPE_VOID
                ? null
                : $input->getValue(0, $inputType);
        } catch (\Throwable $e) {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf(
                    'Failed deserializing input for %s/%s as %s: %s',
                    $context->service,
                    $context->operation,
                    $inputType->getName(),
                    $e->getMessage(),
                ),
                $e,
            );
        }
    }

    /**
     * @param non-empty-string $method
     */
    private function dispatch(NexusContext $dispatchContext, \Closure $terminal, string $method, object $input): mixed
    {
        Nexus::setCurrentContext($dispatchContext);
        try {
            return $this->interceptorProvider
                ->getPipeline(NexusOperationInboundCallsInterceptor::class)
                ->with($terminal, $method)($input);
        } finally {
            Nexus::setCurrentContext(null);
        }
    }

    /**
     * @return array{NexusServiceInstance, OperationHandlerInterface}
     */
    private function resolveHandler(OperationContext $context): array
    {
        $instance = $this->instances[$context->service] ?? null;
        if ($instance === null) {
            throw HandlerException::create(
                ErrorType::NotFound,
                "Unrecognized service '{$context->service}'",
            );
        }

        $handler = $instance->operationHandlers[$context->operation] ?? null;
        if ($handler === null) {
            throw HandlerException::create(
                ErrorType::NotFound,
                "Service '{$context->service}' has no operation '{$context->operation}'",
            );
        }

        return [$instance, $handler];
    }

    private function encodeResult(
        mixed $result,
        OperationContext $context,
        Type $outputType,
    ): ValuesInterface {
        try {
            $payload = $this->dataConverter->toPayload($result);
            $payloads = new Payloads(['payloads' => [$payload]]);
            return EncodedValues::fromPayloads($payloads, $this->dataConverter);
        } catch (\Throwable $e) {
            throw HandlerException::create(
                ErrorType::Internal,
                \sprintf(
                    'Failed serializing result for %s/%s as %s: %s',
                    $context->service,
                    $context->operation,
                    $outputType->getName(),
                    $e->getMessage(),
                ),
                $e,
            );
        }
    }
}
