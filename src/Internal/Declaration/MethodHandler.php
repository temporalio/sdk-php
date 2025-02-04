<?php

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\Dispatcher\AutowiredPayloads;

/**
 * @internal
 */
final class MethodHandler
{
    private readonly AutowiredPayloads $dispatcher;

    public function __construct(
        private readonly object $instance,
        \ReflectionFunctionAbstract $reflection,
    ) {
        $this->dispatcher = new AutowiredPayloads($reflection);
    }

    /**
     * Resolve arguments for the method.
     */
    public function resolveArguments(ValuesInterface $values): array
    {
        return $this->dispatcher->resolveArguments($values);
    }

    public function __invoke(ValuesInterface $values): mixed
    {
        $arguments = $this->resolveArguments($values);
        return $this->dispatcher->dispatch($this->instance, $arguments);
    }
}
