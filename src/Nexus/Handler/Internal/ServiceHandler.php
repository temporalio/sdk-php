<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Interceptor\NexusOperationInbound\NexusOperationCancelInput;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationStartInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Handler\SyncOperationStartResult;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Nexus\Serializer\Internal\Content;
use Temporal\Nexus\Serializer\Internal\SerializerInterface;

/**
 * Handler that delegates to service implementations.
 */
final class ServiceHandler implements HandlerInterface
{
    /**
     * @param array<string, ServiceImplInstance> $instances
     */
    public function __construct(
        private readonly array $instances,
        private readonly SerializerInterface $serializer,
        private readonly PipelineProvider $interceptorProvider = new SimplePipelineProvider(),
    ) {}

    /**
     * @param ServiceImplInstance[] $instances
     */
    public static function create(
        SerializerInterface $serializer,
        array $instances,
        PipelineProvider $interceptorProvider = new SimplePipelineProvider(),
    ): self {
        if (\count($instances) === 0) {
            throw new InvalidArgumentException('No service instances defined');
        }

        $instancesByName = [];
        foreach ($instances as $instance) {
            $name = $instance->definition->name;
            if (isset($instancesByName[$name])) {
                throw new InvalidArgumentException(
                    "Multiple instances registered for service name '{$name}'",
                );
            }
            $instancesByName[$name] = $instance;
        }

        return new self($instancesByName, $serializer, $interceptorProvider);
    }

    /**
     * @return array<string, ServiceImplInstance>
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    public function startOperation(
        OperationContext $context,
        OperationStartDetails $details,
        HandlerInputContent $input,
        ?NexusOperationContext $nexusOperation = null,
    ): OperationStartResult {
        [$instance, $handler] = $this->resolveHandler($context);

        $contextWithServiceDefinition = $context->withServiceDefinition($instance->definition);
        $definition = $instance->definition->operations[$context->operation];

        try {
            $content = new Content($input->data, $input->headers);
            $inputObject = $this->serializer->deserialize($content, $definition->inputType);
        } catch (\Throwable $e) {
            throw HandlerException::create(
                ErrorType::BadRequest,
                \sprintf(
                    'Failed deserializing input for %s/%s as %s: %s',
                    $context->service,
                    $context->operation,
                    $definition->inputType,
                    $e->getMessage(),
                ),
                $e,
            );
        }

        Nexus::setCurrentContext(new NexusContext(
            operation: $nexusOperation,
            current: $contextWithServiceDefinition,
            startDetails: $details,
        ));
        try {
            $result = $this->interceptorProvider
                ->getPipeline(NexusOperationInboundCallsInterceptor::class)
                ->with(
                    static fn(NexusOperationStartInput $input): OperationStartResult => $handler->start(
                        $input->context,
                        $input->details,
                        $input->input,
                    ),
                    /** @see NexusOperationInboundCallsInterceptor::startNexusOperation() */
                    'startNexusOperation',
                )(new NexusOperationStartInput($contextWithServiceDefinition, $details, $inputObject));
        } finally {
            Nexus::setCurrentContext(null);
        }

        \assert($result instanceof OperationStartResult);

        if (!$result instanceof SyncOperationStartResult) {
            return $result;
        }

        return OperationStartResult::sync(
            $this->resultToContent(
                $result->value,
                $contextWithServiceDefinition,
                $definition->outputType,
            ),
        );
    }

    public function cancelOperation(
        OperationContext $context,
        OperationCancelDetails $details,
        ?NexusOperationContext $nexusOperation = null,
    ): void {
        [$instance, $handler] = $this->resolveHandler($context);

        $definition = $instance->definition->operations[$context->operation];
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

        $contextWithServiceDefinition = $context->withServiceDefinition($instance->definition);

        Nexus::setCurrentContext(new NexusContext(
            operation: $nexusOperation,
            current: $contextWithServiceDefinition,
            cancelDetails: $details,
        ));
        try {
            $this->interceptorProvider
                ->getPipeline(NexusOperationInboundCallsInterceptor::class)
                ->with(
                    static function (NexusOperationCancelInput $input) use ($handler): void {
                        $handler->cancel($input->context, $input->details);
                    },
                    /** @see NexusOperationInboundCallsInterceptor::cancelNexusOperation() */
                    'cancelNexusOperation',
                )(new NexusOperationCancelInput($contextWithServiceDefinition, $details));
        } finally {
            Nexus::setCurrentContext(null);
        }
    }

    /**
     * @return array{ServiceImplInstance, OperationHandlerInterface}
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

    private function resultToContent(
        mixed $result,
        OperationContext $context,
        string $outputType,
    ): HandlerResultContent {
        try {
            $output = $this->serializer->serialize($result);
            return new HandlerResultContent($output->data, $output->headers);
        } catch (\Throwable $e) {
            throw HandlerException::create(
                ErrorType::Internal,
                \sprintf(
                    'Failed serializing result for %s/%s as %s: %s',
                    $context->service,
                    $context->operation,
                    $outputType,
                    $e->getMessage(),
                ),
                $e,
            );
        }
    }
}
