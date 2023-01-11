<?php

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Internal\Declaration\Prototype\PrototypeInterface;

interface InterceptorProvider
{
    /**
     * @param PrototypeInterface $prototype Activity or workflow prototype.
     *
     * @return array<mixed, Interceptor>
     */
    public function getInterceptors(PrototypeInterface $prototype): array;
}
