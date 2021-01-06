<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Workflow\WorkflowContext;
use Temporal\Workflow\WorkflowInfo;

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
     */
    public function __construct(ServiceContainer $services)
    {
        $this->instantiator = new WorkflowInstantiator($services->dataConverter);
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

        $context = new WorkflowContext(
            $this->services,
            $this->services->client,
            $instance,
            $input
        );

        $process = new Process($this->services, $context);
        $this->services->running->add($process);

        $process->start($instance->getHandler(), $context->getArguments());
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
