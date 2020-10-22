<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Declaration\Repository;

use Temporal\Client\Worker\Declaration\Collection;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Workflow\WorkflowDeclaration;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;
use Temporal\Client\Meta\ReaderInterface;

/**
 * @mixin WorkflowRepositoryInterface
 */
trait WorkflowRepositoryTrait
{
    /**
     * @psalm-var CollectionInterface<WorkflowDeclarationInterface>
     *
     * @var CollectionInterface|WorkflowDeclarationInterface[]
     */
    private CollectionInterface $workflows;

    /**
     * {@inheritDoc}
     */
    public function registerWorkflow(object $workflow, bool $overwrite = false): self
    {
        if ($workflow instanceof WorkflowDeclarationInterface) {
            return $this->registerWorkflowDeclaration($workflow, $overwrite);
        }

        $workflows = WorkflowDeclaration::fromObject($workflow, $this->getReader());

        foreach ($workflows as $declaration) {
            $this->registerWorkflowDeclaration($declaration, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerWorkflowDeclaration(WorkflowDeclarationInterface $workflow, bool $overwrite = false): self
    {
        $this->workflows->add($workflow, $overwrite);

        return $this;
    }

    /**
     * @return ReaderInterface
     */
    abstract protected function getReader(): ReaderInterface;

    /**
     * {@inheritDoc}
     */
    public function findWorkflow(string $name): ?WorkflowDeclarationInterface
    {
        return $this->workflows->find($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflows(): iterable
    {
        return $this->workflows;
    }

    /**
     * @return void
     */
    protected function bootWorkflowRepositoryTrait(): void
    {
        $this->workflows = new Collection();
    }
}
