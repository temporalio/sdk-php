<?php

declare(strict_types=1);

namespace Temporal\Testing;

use React\Promise\PromiseInterface;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueSet;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Internal\Transport\Request\UpsertTypedSearchAttributes;
use Temporal\Worker\SearchAttributeInvocationCache\RoadRunnerSearchAttributeInvocationCache;
use Temporal\Worker\SearchAttributeInvocationCache\SearchAttributeInvocationCacheInterface;
use Temporal\Worker\Transport\Command\RequestInterface;

use function React\Promise\resolve;

final class MockSearchAttributeInterceptor implements WorkflowOutboundRequestInterceptor
{
    private SearchAttributeInvocationCacheInterface $cache;

    public function __construct(?SearchAttributeInvocationCacheInterface $cache = null)
    {
        $this->cache = $cache ?? RoadRunnerSearchAttributeInvocationCache::create();
    }

    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        if (!$request instanceof UpsertTypedSearchAttributes) {
            return $next($request);
        }

        foreach ($request->getSearchAttributes() as $update) {
            $entry = $update instanceof ValueSet
                ? [
                    'operation' => SearchAttributeInvocationCacheInterface::OPERATION_SET,
                    'type' => $update->type->value,
                    'value' => $update->value,
                ]
                : [
                    'operation' => SearchAttributeInvocationCacheInterface::OPERATION_UNSET,
                    'type' => $update->type->value,
                ];

            $this->cache->recordUpsert($update->name, $entry);
        }

        return resolve(null);
    }
}
