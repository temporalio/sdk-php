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

    public function __invoke(ValuesInterface $values): mixed
    {
        return $this->dispatcher->dispatchValues($this->instance, $values);
    }
}
