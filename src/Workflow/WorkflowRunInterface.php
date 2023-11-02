<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\DataConverter\Type;
use Temporal\Exception\Client\WorkflowFailedException;

/**
 * Represents a running workflow execution. Can be used to wait for the completion result or error.
 */
interface WorkflowRunInterface
{
    /**
     * Returns attached workflow execution.
     *
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution;

    /**
     * Returns workflow result potentially waiting for workflow to complete.
     * Behind the scene this call performs long poll on Temporal service waiting
     * for workflow completion notification.
     *
     * <code>
     * // Map to array
     * $result = $run->getResult(Type::TYPE_ARRAY);
     *
     * // Map to list of custom class
     * $result = $run->getResult(Type::arrayOf(Message::class));
     * </code>
     *
     * @param string|\ReflectionClass|\ReflectionType|Type|null $type
     * @param int|null $timeout Timeout in seconds. Infinite by the default.
     * @return mixed
     * @throws WorkflowFailedException
     *
     * @see DateInterval
     */
    public function getResult($type = null, int $timeout = null): mixed;
}
