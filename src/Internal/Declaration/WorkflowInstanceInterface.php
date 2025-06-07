<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\UpdateDispatcher;

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

    public function getPrototype(): WorkflowPrototype;

    public function getQueryDispatcher(): QueryDispatcher;

    public function getSignalDispatcher(): SignalDispatcher;

    public function getUpdateDispatcher(): UpdateDispatcher;
}
