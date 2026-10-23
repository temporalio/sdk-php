<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;

interface ActivityStubInterface
{
    public function getOptions(): ActivityOptionsInterface;

    /**
     * @param string $name name of an activity type to execute.
     * @param array $args arguments of the activity.
     */
    public function execute(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): mixed;

    /**
     * @param string $name name of an activity type to execute.
     * @param array $args arguments of the activity.
     * @return PromiseInterface<mixed>
     */
    public function executeAsync(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): PromiseInterface;
}
