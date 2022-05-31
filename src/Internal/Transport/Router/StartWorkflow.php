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
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\WorkflowInfo;

final class StartWorkflow extends Route
{
    private const ERROR_NOT_FOUND = 'Workflow with the specified name "%s" was not registered';

    private ServiceContainer $services;
    private WorkflowInstantiator $instantiator;

    /**
     * @param ServiceContainer $services
     */
    public function __construct(ServiceContainer $services)
    {
        $this->services = $services;
        $this->instantiator = new WorkflowInstantiator();
    }

    /**
     * {@inheritDoc}
     * @throws \Throwable
     */
    public function handle(RequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $payloads = $request->getPayloads();
        $lastCompletionResult = null;

        if (($options['lastCompletion'] ?? 0) !== 0) {
            $offset = count($payloads) - ($options['lastCompletion'] ?? 0);

            $lastCompletionResult = EncodedValues::sliceValues($this->services->dataConverter, $payloads, $offset);
            $payloads = EncodedValues::sliceValues($this->services->dataConverter, $payloads, 0, $offset);
        }

        $input = $this->services->marshaller->unmarshal($options, new Input());
        /** @psalm-suppress InaccessibleProperty */
        $input->input = $payloads;

        $instance = $this->instantiator->instantiate($this->findWorkflowOrFail($input->info));

        $context = new WorkflowContext(
            $this->services,
            $this->services->client,
            $instance,
            $input,
            $lastCompletionResult
        );

        $process = new Process($this->services, $context);
        $this->services->running->add($process);
        $resolver->resolve(EncodedValues::fromValues([null]));

        $process->start($instance->getHandler(), $context->getInput());
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

        return $workflow;
    }
}
