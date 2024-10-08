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
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

interface WorkflowInstanceInterface extends InstanceInterface
{
    /**
     * Trigger constructor in Process context.
     */
    public function initConstructor(): void;

    /**
     * @param non-empty-string $name
     * @return null|\Closure(QueryInput): mixed
     */
    public function findQueryHandler(string $name): ?\Closure;

    /**
     * @param string $name
     * @param callable $handler
     */
    public function addQueryHandler(string $name, callable $handler): void;

    /**
     * @param non-empty-string $name
     * @param callable $handler
     */
    public function addUpdateHandler(string $name, callable $handler): void;

    /**
     * @param non-empty-string $name
     * @param callable $handler
     */
    public function addValidateUpdateHandler(string $name, callable $handler): void;

    /**
     * @param non-empty-string $name
     * @return \Closure(ValuesInterface): void
     */
    public function getSignalHandler(string $name): \Closure;

    /**
     * @param non-empty-string $name
     * @return null|\Closure(UpdateInput, Deferred): PromiseInterface
     */
    public function findUpdateHandler(string $name): ?\Closure;

    /**
     * @param string $name
     * @param callable $handler
     */
    public function addSignalHandler(string $name, callable $handler): void;

    public function clearSignalQueue(): void;

    public function getPrototype(): WorkflowPrototype;
}
