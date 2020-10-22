<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Declaration\Repository;

use Temporal\Client\Workflow\WorkflowDeclarationInterface;

interface WorkflowRepositoryInterface extends RepositoryInterface
{
    /**
     * @param object $workflow
     * @param bool $overwrite
     * @return static
     */
    public function registerWorkflow(object $workflow, bool $overwrite = false): self;

    /**
     * @param WorkflowDeclarationInterface $workflow
     * @param bool $overwrite
     * @return static
     */
    public function registerWorkflowDeclaration(WorkflowDeclarationInterface $workflow, bool $overwrite = false): self;

    /**
     * @param string $name
     * @return WorkflowDeclarationInterface|null
     */
    public function findWorkflow(string $name): ?WorkflowDeclarationInterface;

    /**
     * @return iterable|WorkflowDeclarationInterface[]
     */
    public function getWorkflows(): iterable;
}
