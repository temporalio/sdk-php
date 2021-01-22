<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Workflow;

use Temporal\DataConverter\Type;

interface WorkflowRunInterface
{
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Returns attached workflow execution.
     *
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution;

    /**
     * Get execution result value.
     *
     * @param Type|string $type
     * @param int $timeout
     * @return mixed
     */
    public function getResult($type = null, int $timeout = self::DEFAULT_TIMEOUT);
}
