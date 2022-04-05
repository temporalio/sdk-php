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
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Transport\CompletableResultInterface;

interface ActivityStubInterface
{
    /**
     * @return ActivityOptionsInterface
     */
    public function getOptions(): ActivityOptionsInterface;

    /**
     * Executes an activity asynchronously by its type name and arguments.
     *
     * @param string $name name of an activity type to execute.
     * @param array $args arguments of the activity.
     * @param Type|string|null|\ReflectionClass|\ReflectionType $returnType
     * @param bool $isLocalActivity
     * @return CompletableResultInterface Promise to the activity result.
     */
    public function execute(string $name, array $args = [], $returnType = null, bool $isLocalActivity = false): PromiseInterface;
}
