<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;

/**
 * @experimental
 */
interface FiberActivityStubInterface
{
    public function getOptions(): ActivityOptionsInterface;

    /**
     * Execute the activity and return its resolved result.
     *
     * @param list<mixed> $args
     */
    public function execute(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): mixed;

    /**
     * Start the activity and return the underlying promise for parallel composition.
     *
     * @param list<mixed> $args
     */
    public function executeAsync(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): PromiseInterface;
}
