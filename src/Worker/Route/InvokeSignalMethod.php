<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Route;

use React\Promise\Deferred;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Workflow\Declaration\WorkflowDeclarationInterface;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;

final class InvokeSignalMethod extends Route
{
    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @psalm-var CollectionInterface<WorkflowDeclarationInterface>
     *
     * @var CollectionInterface
     */
    private CollectionInterface $workflows;

    /**
     * @param CollectionInterface<WorkflowDeclarationInterface> $workflows
     * @param RunningWorkflows $running
     */
    public function __construct(CollectionInterface $workflows, RunningWorkflows $running)
    {
        $this->running = $running;
        $this->workflows = $workflows;
    }

    /**
     * @param array $params
     * @param Deferred $resolver
     */
    public function handle(array $params, Deferred $resolver): void
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
