<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\Trait\WorkflowOutboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowOutboundCalls\SideEffectInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Worker\ChildWorkflowInvocationCache\ChildWorkflowInvocationCacheInterface;
use Temporal\Worker\ChildWorkflowInvocationCache\RoadRunnerChildWorkflowInvocationCache;

final class MockSideEffectInterceptor implements WorkflowOutboundCallsInterceptor
{
    use WorkflowOutboundCallsInterceptorTrait;

    private ChildWorkflowInvocationCacheInterface $cache;

    public function __construct(
        ?ChildWorkflowInvocationCacheInterface $cache = null,
        ?DataConverterInterface $dataConverter = null,
    ) {
        $this->cache = $cache ?? RoadRunnerChildWorkflowInvocationCache::create(
            $dataConverter ?? DataConverter::createDefault(),
        );
    }

    public function sideEffect(SideEffectInput $input, callable $next): mixed
    {
        if (!$this->cache->hasSideEffect()) {
            return $next($input);
        }

        return $this->cache->nextSideEffect();
    }
}
