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
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\FeatureFlags;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInfo;

final class StartWorkflow extends Route
{
    private const ERROR_NOT_FOUND = 'Workflow with the specified name "%s" was not registered';

    private readonly WorkflowInstantiator $instantiator;
    private readonly bool $wfStartDeferred;

    public function __construct(
        private readonly ServiceContainer $services,
    ) {
        $this->wfStartDeferred = FeatureFlags::$workflowDeferredHandlerStart;
        $this->instantiator = new WorkflowInstantiator($services->interceptorProvider);
    }

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

        // Search Attributes
        $searchAttributes = $this->convertSearchAttributes($options['info']['SearchAttributes'] ?? null);
        $options['info']['SearchAttributes'] = $searchAttributes?->getValues();

        /** @var Input $input */
        $input = $this->services->marshaller->unmarshal($options, new Input());

        /** @psalm-suppress InaccessibleProperty */
        $input->input = $payloads;
        /** @psalm-suppress InaccessibleProperty */
        $input->header = $request->getHeader();

        $info = $input->info;
        $tickInfo = $request->getTickInfo();
        /** @psalm-suppress InaccessibleProperty */
        $info->historyLength = $tickInfo->historyLength;
        /** @psalm-suppress InaccessibleProperty */
        $info->historySize = $tickInfo->historySize;
        /** @psalm-suppress InaccessibleProperty */
        $info->shouldContinueAsNew = $tickInfo->continueAsNewSuggested;

        $instance = $this->instantiator->instantiate($this->findWorkflowOrFail($input->info));

        $context = new WorkflowContext(
            $this->services,
            $this->services->client,
            $instance,
            $input,
            $lastCompletionResult,
        );
        $runId = $request->getID();

        $starter = function (WorkflowInput $input) use (
            $resolver,
            $instance,
            $context,
            $runId,
        ): void {
            $context = $context->withInput(new Input($input->info, $input->arguments, $input->header));
            $process = new Process($this->services, $context, $runId);
            $this->services->running->add($process);
            $resolver->resolve(EncodedValues::fromValues([null]));

            $process->start($instance->getHandler(), $context->getInput(), $this->wfStartDeferred);
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

    private function findWorkflowOrFail(WorkflowInfo $info): WorkflowPrototype
    {
        return $this->services->workflows->find($info->type->name) ?? throw new \OutOfRangeException(
            \sprintf(self::ERROR_NOT_FOUND, $info->type->name),
        );
    }

    private function convertSearchAttributes(?array $param): ?EncodedCollection
    {
        if (!\is_array($param)) {
            return null;
        }

        if ($param === []) {
            return EncodedCollection::empty();
        }

        try {
            $sa = (new SearchAttributes());
            $sa->mergeFromJsonString(
                \json_encode($param),
                true,
            );

            return EncodedCollection::fromPayloadCollection(
                $sa->getIndexedFields(),
                $this->services->dataConverter,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
