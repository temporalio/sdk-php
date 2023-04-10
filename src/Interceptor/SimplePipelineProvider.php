<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Interceptor\Pipeline;

class SimplePipelineProvider implements PipelineProvider
{
    private array $cache = [];

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
    public function getPipeline(string $interceptorClass): Pipeline
    {
        return $this->cache[$interceptorClass] ??= Pipeline::prepare(\array_filter(
            $this->interceptors,
            static fn(Interceptor $i): bool => $i instanceof $interceptorClass)
        );
    }
}
