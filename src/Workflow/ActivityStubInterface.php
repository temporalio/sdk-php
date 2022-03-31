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
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\Type;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Transport\CompletableResultInterface;

interface ActivityStubInterface
{
    /**
     * @return ActivityOptions
     */
    public function getOptions(): ActivityOptions;

    /**
     * Executes an activity asynchronously by its type name and arguments.
     *
     * @param ActivityPrototype $handler activity prototype to execute.
     * @param array $args arguments of the activity.
     * @param Type|string|null|\ReflectionClass|\ReflectionType $returnType
     * @return CompletableResultInterface Promise to the activity result.
     */
    public function execute(ActivityPrototype $handler, array $args = [], $returnType = null): PromiseInterface;
}
