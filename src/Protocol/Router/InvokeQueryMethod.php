<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Router;

use React\Promise\Deferred;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;

final class InvokeQueryMethod extends Route
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
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers)
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
