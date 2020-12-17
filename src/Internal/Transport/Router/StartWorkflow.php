<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Internal\Workflow\Input;
use Temporal\Client\Internal\Workflow\Process\Process;
use Temporal\Client\Workflow\WorkflowInfo;

final class StartWorkflow extends Route
{
    /**
     * @var string
     */
    private const ERROR_NOT_FOUND = 'Workflow with the specified name "%s" was not registered';

    /**
     * @var string
     */
    private const ERROR_ALREADY_RUNNING = 'Workflow "%s" with run id "%s" has been already started';

    /**
     * @var ServiceContainer
     */
    private ServiceContainer $services;

    /**
     * @var WorkflowInstantiator
     */
    private WorkflowInstantiator $instantiator;

    /**
     * @param ServiceContainer $services
     * @param RepositoryInterface $running
     */
    public function __construct(ServiceContainer $services)
    {
        $this->instantiator = new WorkflowInstantiator();
        $this->services = $services;
    }

    /**
     * {@inheritDoc}
     * @throws \Throwable
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $input = $this->services->marshaller->unmarshal($payload, new Input());

        $instance = $this->instantiator->instantiate(
            $this->findWorkflowOrFail($input->info)
        );

        new Process($input, $this->services, $instance);
    }

    /**
     * @param WorkflowInfo $info
     * @return WorkflowPrototype
     */
    private function findWorkflowOrFail(WorkflowInfo $info): WorkflowPrototype
    {
        $workflow = $this->services->workflows->find($info->type->name);

        if ($workflow === null) {
            throw new \OutOfRangeException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        if ($this->services->running->find($info->execution->runId) !== null) {
            $message = \sprintf(self::ERROR_ALREADY_RUNNING, $info->type->name, $info->execution->runId);

            throw new \LogicException($message);
        }

        return $workflow;
    }
}
