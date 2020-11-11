<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance;

use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

/**
 * @template-implements InstanceInterface<WorkflowInterface, WorkflowMethod>
 *
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
interface WorkflowInstanceInterface extends InstanceInterface
{
    /**
     * @return WorkflowInterface
     */
    public function getMetadata(): WorkflowInterface;

    /**
     * @return WorkflowMethod
     */
    public function getMethod(): WorkflowMethod;

    /**
     * @psalm-return iterable<QueryMethod, DispatchableHandler>
     *
     * @return callable[]
     */
    public function getQueryHandlers(): iterable;

    /**
     * @psalm-return iterable<SignalMethod, DispatchableHandler>
     *
     * @return callable[]
     */
    public function getSignalHandlers(): iterable;
}
