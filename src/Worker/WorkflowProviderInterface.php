<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Declaration\WorkflowInterface;

interface WorkflowProviderInterface
{
    /**
     * @param object $workflow
     * @param bool $overwrite
     */
    public function addWorkflow(object $workflow, bool $overwrite = false): void;

    /**
     * @param WorkflowInterface $workflow
     * @param bool $overwrite
     */
    public function addWorkflowDeclaration(WorkflowInterface $workflow, bool $overwrite = false): void;

    /**
     * @param string $name
     * @return WorkflowInterface|null
     */
    public function findWorkflow(string $name): ?WorkflowInterface;

    /**
     * @return iterable|WorkflowInterface[]
     */
    public function getWorkflows(): iterable;
}
