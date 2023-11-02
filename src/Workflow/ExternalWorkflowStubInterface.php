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
use Temporal\Internal\Transport\CompletableResultInterface;

interface ExternalWorkflowStubInterface
{
    /**
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution;

    /**
     * @param string $name
     * @param array $args
     *
     * @return PromiseInterface
     * @throws \LogicException
     */
    public function signal(string $name, array $args = []): PromiseInterface;

    /**
     * @return PromiseInterface
     */
    public function cancel(): PromiseInterface;
}
