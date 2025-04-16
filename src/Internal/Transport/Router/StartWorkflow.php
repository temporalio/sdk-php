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
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Common\TypedSearchAttributes;
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

        // Search Attributes and Typed Search Attributes
        $searchAttributes = $this->convertSearchAttributes($options['info']['SearchAttributes'] ?? null);
        $memo = $this->convertMemo($options['info']['Memo'] ?? null);
        $options['info']['SearchAttributes'] = $searchAttributes?->getValues();
        $options['info']['TypedSearchAttributes'] = $this->prepareTypedSA($options['search_attributes'] ?? null);
        $options['info']['Memo'] = $memo?->getValues();

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
            $this->services->client->fork(),
            $instance,
            $input,
            $lastCompletionResult,
        );
        $runId = $request->getID();

        Workflow::setCurrentContext($context);
        $process = new Process($this->services, $context, $runId);
        $this->services->running->add($process);
        $resolver->resolve(EncodedValues::fromValues([null]));
        $process->initAndStart($instance, $this->wfStartDeferred);
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

    private function convertMemo(?array $param): ?EncodedCollection
    {
        if (!\is_array($param)) {
            return null;
        }

        if ($param === []) {
            return EncodedCollection::empty();
        }

        try {
            $memo = (new Memo());
            $memo->mergeFromJsonString(
                \json_encode($param),
                true,
            );

            return EncodedCollection::fromPayloadCollection(
                $memo->getFields(),
                $this->services->dataConverter,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function prepareTypedSA(?array $param): TypedSearchAttributes
    {
        return $param === null
            ? TypedSearchAttributes::empty()
            : TypedSearchAttributes::fromJsonArray($param);
    }
}
