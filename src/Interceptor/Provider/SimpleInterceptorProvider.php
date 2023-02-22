<?php

declare(strict_types=1);

namespace Temporal\Interceptor\Provider;

use Temporal\Interceptor\Interceptor;
use Temporal\Interceptor\InterceptorProvider;

/**
 * Provide the same static list of interceptors for all instance types.
 */
class SimpleInterceptorProvider implements InterceptorProvider
{
    /**
     * @param array<array-key, Interceptor> $interceptors
     */
    public function __construct(
        private iterable $interceptors = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getInterceptors(?string $type = null): array
    {
        return $type === null
            ? $this->interceptors
            : \array_filter($this->interceptors, static fn(Interceptor $i): bool => $i instanceof $type);
    }
}
