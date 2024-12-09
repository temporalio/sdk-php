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

interface ExternalWorkflowStubInterface
{
    public function getExecution(): WorkflowExecution;

    /**
     *
     * @throws \LogicException
     */
    public function signal(string $name, array $args = []): PromiseInterface;

    public function cancel(): PromiseInterface;
}
