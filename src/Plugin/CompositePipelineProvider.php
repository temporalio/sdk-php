<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

use Temporal\Interceptor\PipelineProvider;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * Pipeline provider that merges plugin-contributed interceptors with a base provider.
 *
 * Plugin interceptors are prepended before base provider interceptors,
 * so they execute first in the pipeline chain.
 *
 * @internal
 */
final class CompositePipelineProvider implements PipelineProvider
{
    private array $cache = [];

    /**
     * @param list<Interceptor> $pluginInterceptors Interceptors contributed by plugins.
     * @param PipelineProvider $baseProvider The original user-provided pipeline provider.
     */
    public function __construct(
        private readonly array $pluginInterceptors,
        private readonly PipelineProvider $baseProvider,
    ) {}

    public function getPipeline(string $interceptorClass): Pipeline
    {
        if (isset($this->cache[$interceptorClass])) {
            return $this->cache[$interceptorClass];
        }

        $basePipeline = $this->baseProvider->getPipeline($interceptorClass);

        if ($this->pluginInterceptors === []) {
            return $this->cache[$interceptorClass] = $basePipeline;
        }

        $filtered = \array_filter(
            $this->pluginInterceptors,
            static fn(Interceptor $i): bool => $i instanceof $interceptorClass,
        );

        if ($filtered === []) {
            return $this->cache[$interceptorClass] = $basePipeline;
        }

        return $this->cache[$interceptorClass] = Pipeline::merge(Pipeline::prepare($filtered), $basePipeline);
    }
}
