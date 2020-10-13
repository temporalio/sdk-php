<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Declaration\Collection;
use Temporal\Client\Declaration\CollectionInterface;
use Temporal\Client\Declaration\DeclarationInterface;
use Temporal\Client\Declaration\Workflow;
use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Meta\ReaderInterface;

/**
 * @mixin WorkflowProviderInterface
 */
trait WorkflowProviderTrait
{
    /**
     * @psalm-var CollectionInterface<WorkflowInterface>
     *
     * @var CollectionInterface|WorkflowInterface[]
     */
    private CollectionInterface $workflows;

    /**
     * {@inheritDoc}
     */
    public function addWorkflow(object $workflow, bool $overwrite = false): void
    {
        if ($workflow instanceof WorkflowInterface) {
            $this->addWorkflowDeclaration($workflow, $overwrite);

            return;
        }

        $workflows = Workflow::fromObject($workflow, $this->getMetadataReader());

        foreach ($workflows as $declaration) {
            $this->addWorkflowDeclaration($declaration, $overwrite);
        }
    }

    /**
     * @return ReaderInterface
     */
    abstract protected function getMetadataReader(): ReaderInterface;

    /**
     * {@inheritDoc}
     */
    public function addWorkflowDeclaration(WorkflowInterface $workflow, bool $overwrite = false): void
    {
        $this->workflows->add($workflow, $overwrite);
    }

    /**
     * @param string $name
     * @return DeclarationInterface|WorkflowInterface|null
     */
    public function findWorkflow(string $name): ?WorkflowInterface
    {
        return $this->workflows->find($name);
    }

    /**
     * @return void
     */
    protected function bootWorkflowProviderTrait(): void
    {
        $this->workflows = new Collection();
    }
}
