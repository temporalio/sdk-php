<?php

declare(strict_types=1);

namespace Temporal\Interceptor;

interface InterceptorProvider
{
    /**
     * @template T of Interceptor
     *
     * @param class-string<T>|null $type If specified then only interceptors of this type will be returned. Otherwise
     *        all interceptors will be returned.
     *
     * @return ($type is null ? array<mixed, Interceptor> : array<mixed, T>)
     */
    public function getInterceptors(?string $type = null): array;
}
