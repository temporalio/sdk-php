<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use Temporal\Client\Workflow\Declaration\WorkflowDeclarationInterface;

/**
 * @internal Process is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Workflow
 */
final class Process
{
    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @var WorkflowDeclarationInterface
     */
    private WorkflowDeclarationInterface $declaration;

    /**
     * @param WorkflowContextInterface $context
     * @param WorkflowDeclarationInterface $declaration
     */
    public function __construct(WorkflowContextInterface $context, WorkflowDeclarationInterface $declaration)
    {
        $this->context = $context;
        $this->declaration = clone $declaration;
    }

    /**
     * @return WorkflowContextInterface
     */
    public function getContext(): WorkflowContextInterface
    {
        return $this->context;
    }

    /**
     * @return WorkflowDeclarationInterface
     */
    public function getDeclaration(): WorkflowDeclarationInterface
    {
        return $this->declaration;
    }

    public function run(callable $handler)
    {
        // TODO
    }
}
