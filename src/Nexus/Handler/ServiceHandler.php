<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Serializer\Content;
use Temporal\Nexus\Serializer\SerializerInterface;

/**
 * Handler that delegates to service implementations.
 */
final class ServiceHandler implements HandlerInterface
{
    /**
     * @param array<string, ServiceImplInstance> $instances
     * @param OperationMiddlewareInterface[] $middlewares
     */
    public function __construct(
        private readonly array $instances,
        private readonly SerializerInterface $serializer,
        private readonly array $middlewares = [],
    ) {}

    /**
     * @param ServiceImplInstance[] $instances
     * @param OperationMiddlewareInterface[] $middlewares
     */
    public static function create(
        SerializerInterface $serializer,
        array $instances,
        array $middlewares = [],
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

        return new self($instancesByName, $serializer, $middlewares);
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

    /**
     * @return OperationMiddlewareInterface[]
     */
    public function getOperationMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function startOperation(
        OperationContext $context,
        OperationStartDetails $details,
        HandlerInputContent $input,
    ): OperationStartResult {
        [$instance, $handler] = $this->resolveHandler($context);

        $contextWithServiceDef = $context->withServiceDefinition($instance->definition);
        $interceptedHandler = $this->interceptOperationHandler($contextWithServiceDef, $handler);
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

        $result = $interceptedHandler->start($contextWithServiceDef, $details, $inputObject);

        if (!$result instanceof SyncOperationStartResult) {
            return $result;
        }

        return OperationStartResult::sync(
            $this->resultToContent(
                $result->value,
                $contextWithServiceDef,
                $definition->outputType,
            ),
        );
    }

    public function cancelOperation(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        [$instance, $handler] = $this->resolveHandler($context);

        if ($handler instanceof SynchronousOperationHandler) {
            throw HandlerException::create(
                ErrorType::NotImplemented,
                \sprintf(
                    'Operation %s/%s is synchronous and cannot be cancelled',
                    $context->service,
                    $context->operation,
                ),
            );
        }

        $contextWithServiceDef = $context->withServiceDefinition($instance->definition);
        $this->interceptOperationHandler($contextWithServiceDef, $handler)
            ->cancel($contextWithServiceDef, $details);
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

    private function interceptOperationHandler(
        OperationContext $context,
        OperationHandlerInterface $rootHandler,
    ): OperationHandlerInterface {
        $handler = $rootHandler;
        for ($i = \count($this->middlewares) - 1; $i >= 0; $i--) {
            $handler = $this->middlewares[$i]->intercept($context, $handler);
        }
        return $handler;
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
