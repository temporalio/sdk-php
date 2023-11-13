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
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInfo;

final class StartWorkflow extends Route
{
    private const ERROR_NOT_FOUND = 'Workflow with the specified name "%s" was not registered';

    private WorkflowInstantiator $instantiator;

    /**
     * @param ServiceContainer $services
     */
    public function __construct(
        private ServiceContainer $services,
    ) {
        $this->instantiator = new WorkflowInstantiator($services->interceptorProvider);
    }

    /**
     * {@inheritDoc}
     * @throws \Throwable
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();
        $payloads = $request->getPayloads();
        $lastCompletionResult = null;

        if (($options['lastCompletion'] ?? 0) !== 0) {
            $offset = \count($payloads) - ($options['lastCompletion'] ?? 0);

            $lastCompletionResult = EncodedValues::sliceValues($this->services->dataConverter, $payloads, $offset);
            $payloads = EncodedValues::sliceValues($this->services->dataConverter, $payloads, 0, $offset);
        }

        /** @var Input $input */
        $input = $this->services->marshaller->unmarshal($options, new Input());
        /** @psalm-suppress InaccessibleProperty */
        $input->input = $payloads;
        /** @psalm-suppress InaccessibleProperty */
        $input->header = $request->getHeader();
        /** @psalm-suppress InaccessibleProperty */
        $input->info->historyLength = $request->getHistoryLength();

        $instance = $this->instantiator->instantiate($this->findWorkflowOrFail($input->info));

        $context = new WorkflowContext(
            $this->services,
            $this->services->client,
            $instance,
            $input,
            $lastCompletionResult
        );
        $runId = $request->getID();

        $starter = function (WorkflowInput $input) use (
            $resolver,
            $instance,
            $context,
            $runId,
        ) {
            $context = $context->withInput(new Input($input->info, $input->arguments, $input->header));
            $process = new Process($this->services, $context, $runId);
            $this->services->running->add($process);
            $resolver->resolve(EncodedValues::fromValues([null]));

            $process->start($instance->getHandler(), $context->getInput());
        };

        // Define Context for interceptors Pipeline
        Workflow::setCurrentContext($context);

        // Run workflow handler in an interceptor pipeline
        $this->services->interceptorProvider
            ->getPipeline(WorkflowInboundCallsInterceptor::class)
            ->with(
                $starter,
                /** @see WorkflowInboundCallsInterceptor::execute() */
                'execute',
            )(
                new WorkflowInput($context->getInfo(), $context->getInput(), $context->getHeader()),
            );
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
