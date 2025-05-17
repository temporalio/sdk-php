<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;

/**
 * @internal
 */
interface WorkflowInstanceInterface extends InstanceInterface
{
    /**
     * Trigger constructor in Process context.
     * If the constructor is tagged with {@see \Temporal\Workflow\WorkflowInit} attribute,
     * it will be executed with the {@see \Temporal\Workflow\WorkflowMethod} arguments.
     */
    public function init(array $arguments = []): void;

    /**
     * @param non-empty-string $name
     */
    public function addUpdateHandler(string $name, callable $handler): void;

    /**
     * @param non-empty-string $name
     */
    public function addValidateUpdateHandler(string $name, callable $handler): void;

    /**
     * @param non-empty-string $name
     * @return null|\Closure(UpdateInput, Deferred): PromiseInterface
     */
    public function findUpdateHandler(string $name): ?\Closure;

    /**
     * @param callable(non-empty-string, ValuesInterface): mixed $handler
     * @param null|callable(non-empty-string, ValuesInterface): mixed $validator
     */
    public function setDynamicUpdateHandler(callable $handler, ?callable $validator = null): void;

    public function getPrototype(): WorkflowPrototype;

    public function getQueryDispatcher(): QueryDispatcher;

    public function getSignalDispatcher(): SignalDispatcher;
}
