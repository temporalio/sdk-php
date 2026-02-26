<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;
use Temporal\Workflow\ActivityStubInterface;

/**
 * Fiber-friendly decorator for {@see ActivityStubInterface}.
 *
 * Wraps all PromiseInterface-returning methods with {@see FiberHelper::await()},
 * so the caller gets resolved values instead of promises.
 *
 * @experimental
 * @internal
 */
final class FiberActivityStub
{
    public function __construct(
        private readonly ActivityStubInterface $inner,
    ) {}

    public function getOptions(): ActivityOptionsInterface
    {
        return $this->inner->getOptions();
    }

    public function execute(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): mixed {
        return FiberHelper::await($this->inner->execute($name, $args, $returnType, $isLocalActivity));
    }

    public function createExecution(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): PromiseInterface {
        return $this->inner->execute($name, $args, $returnType, $isLocalActivity);
    }
}
