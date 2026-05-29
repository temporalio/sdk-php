<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\OperationInfo;

/**
 * @internal
 * @implements OperationHandlerInterface<mixed, mixed>
 */
final class MethodOperationHandler implements OperationHandlerInterface
{
    public function __construct(
        private readonly object $instance,
        private readonly \ReflectionMethod $startMethod,
        private readonly NexusOperationPrototype $operation,
    ) {}

    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        $args = $this->startMethod->getNumberOfParameters() === 0 ? [] : [$param];
        $result = $this->startMethod->invoke($this->instance, ...$args);

        // Method's declared return type is enforced by PHP at invocation time:
        // `: OperationInfo` for async, the wire output type for sync.
        if ($this->operation->async) {
            \assert($result instanceof OperationInfo);
            return OperationStartResult::async($result);
        }

        return OperationStartResult::sync($result);
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        $cancelMethod = $this->operation->cancelHandler;

        if ($cancelMethod === null) {
            throw HandlerException::create(
                ErrorType::NotImplemented,
                \sprintf(
                    'Operation %s/%s does not declare a cancel routine',
                    $context->service,
                    $context->operation,
                ),
            );
        }

        $cancelMethod->invoke($this->instance, ...$this->resolveCancelArgs($cancelMethod, $context, $details));
    }

    /**
     * @return list<mixed>
     */
    private function resolveCancelArgs(
        \ReflectionMethod $cancelMethod,
        OperationContext $context,
        OperationCancelDetails $details,
    ): array {
        $args = [];
        foreach ($cancelMethod->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            $args[] = match ($typeName) {
                OperationContext::class => $context,
                OperationCancelDetails::class => $details,
                default => $details->operationToken,
            };
        }

        return $args;
    }
}
