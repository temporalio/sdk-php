<?php

declare(strict_types=1);

namespace Temporal\Testing;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Api\Enums\V1\RetryState;
use Temporal\Client\ClientOptions;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\GetChildWorkflowExecution;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Worker\ChildWorkflowInvocationCache\ChildWorkflowInvocationCacheInterface;
use Temporal\Worker\ChildWorkflowInvocationCache\RoadRunnerChildWorkflowInvocationCache;
use Temporal\Worker\InvocationFailure;
use Temporal\Worker\InvocationMatched;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\WorkflowExecution;

use function React\Promise\reject;
use function React\Promise\resolve;

final class MockChildWorkflowInterceptor implements WorkflowOutboundRequestInterceptor
{
    private ChildWorkflowInvocationCacheInterface $cache;
    private DataConverterInterface $dataConverter;

    /** @var array<int, true> */
    private array $mockedRequestIds = [];

    public function __construct(
        ?ChildWorkflowInvocationCacheInterface $cache = null,
        ?DataConverterInterface $dataConverter = null,
    ) {
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();
        $this->cache = $cache ?? RoadRunnerChildWorkflowInvocationCache::create($this->dataConverter);
    }

    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        if ($request instanceof ExecuteChildWorkflow) {
            return $this->handleExecuteChildWorkflow($request, $next);
        }

        if ($request instanceof GetChildWorkflowExecution) {
            return $this->handleGetChildWorkflowExecution($request, $next);
        }

        if ($request instanceof GetVersion) {
            return $this->handleGetVersion($request, $next);
        }

        return $next($request);
    }

    private function handleGetVersion(GetVersion $request, callable $next): PromiseInterface
    {
        $changeId = $request->getChangeId();

        if (!$this->cache->hasVersion($changeId)) {
            return $next($request);
        }

        return resolve(EncodedValues::fromValues([$this->cache->getVersion($changeId)]));
    }

    private function handleExecuteChildWorkflow(ExecuteChildWorkflow $request, callable $next): PromiseInterface
    {
        $workflowType = $request->getWorkflowType();

        if (!$this->cache->has($workflowType)) {
            return $next($request);
        }

        $value = $this->cache->get($workflowType);

        if ($value instanceof InvocationMatched) {
            $input = EncodedValues::fromValues($request->getPayloads()->getValues(), $this->dataConverter)->toPayloads();
            $matched = $value->match($input);
            if ($matched === null) {
                return $next($request);
            }
            $value = $matched;
        }

        if ($value instanceof InvocationFailure) {
            $this->mockedRequestIds[$request->getID()] = true;
            $this->cache->recordInvoked($workflowType);

            return reject(new ChildWorkflowFailure(
                0,
                0,
                $workflowType,
                $this->mockedExecution($request->getID()),
                $request->getOptions()['options']['Namespace'] ?? ClientOptions::DEFAULT_NAMESPACE,
                RetryState::RETRY_STATE_UNSPECIFIED,
                $value->toThrowable($this->dataConverter),
            ));
        }

        $this->mockedRequestIds[$request->getID()] = true;
        $this->cache->recordInvoked($workflowType);

        return resolve($value->toEncodedValues($this->dataConverter));
    }

    private function handleGetChildWorkflowExecution(GetChildWorkflowExecution $request, callable $next): PromiseInterface
    {
        $id = $request->getOptions()['id'] ?? null;

        if (!\is_int($id) || !isset($this->mockedRequestIds[$id])) {
            return $next($request);
        }

        return resolve(EncodedValues::fromValues([$this->mockedExecution($id)]));
    }

    private function mockedExecution(int $id): WorkflowExecution
    {
        return new WorkflowExecution('mocked-child-' . $id, 'mocked-run-' . $id);
    }
}
