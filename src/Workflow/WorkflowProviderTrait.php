<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Worker\Declaration\Collection;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Worker\Declaration\DeclarationInterface;
use Temporal\Client\Workflow\Declaration\WorkflowDeclaration;
use Temporal\Client\Workflow\Declaration\WorkflowDeclarationInterface;

/**
 * @mixin WorkflowProviderInterface
 */
trait WorkflowProviderTrait
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
    public function withWorkflow(object $workflow, bool $overwrite = false): WorkflowProviderInterface
    {
        if ($workflow instanceof WorkflowDeclarationInterface) {
            return $this->withWorkflowDeclaration($workflow, $overwrite);
        }

        $workflows = WorkflowDeclaration::fromObject($workflow, $this->getMetadataReader());

        $self = clone $this;

        foreach ($workflows as $declaration) {
            $self = $self->withWorkflowDeclaration($declaration, $overwrite);
        }

        return $self;
    }

    /**
     * {@inheritDoc}
     * @return $this
     */
    public function withWorkflowDeclaration(
        WorkflowDeclarationInterface $workflow,
        bool $overwrite = false
    ): WorkflowProviderInterface {
        $self = clone $this;
        $self->workflows->add($workflow, $overwrite);

        return $self;
    }

    /**
     * @return ReaderInterface
     */
    abstract protected function getMetadataReader(): ReaderInterface;

    /**
     * @param string $name
     * @return DeclarationInterface|WorkflowDeclarationInterface|null
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
    protected function bootWorkflowProviderTrait(): void
    {
        $this->workflows = new Collection();
    }
}
