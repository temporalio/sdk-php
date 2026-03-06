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
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * Pipeline provider that merges plugin-contributed interceptors with a base provider.
 *
 * If the base provider is a {@see SimplePipelineProvider}, interceptors are prepended directly.
 * For other providers, only plugin interceptors are used (base provider is replaced).
 *
 * @internal
 */
final class CompositePipelineProvider implements PipelineProvider
{
    private readonly PipelineProvider $delegate;

    /**
     * @param list<Interceptor> $pluginInterceptors Interceptors contributed by plugins.
     * @param PipelineProvider $baseProvider The original user-provided pipeline provider.
     */
    public function __construct(
        array $pluginInterceptors,
        PipelineProvider $baseProvider,
    ) {
        $this->delegate = match (true) {
            $pluginInterceptors === [] => $baseProvider,
            $baseProvider instanceof SimplePipelineProvider => $baseProvider->withPrependedInterceptors($pluginInterceptors),
            default => new class($pluginInterceptors, $baseProvider) implements PipelineProvider {
                /** @var array<string, Pipeline> */
                private array $cache = [];

                /**
                 * @param list<Interceptor> $pluginInterceptors
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

                    $filtered = \array_filter(
                        $this->pluginInterceptors,
                        static fn(Interceptor $i): bool => $i instanceof $interceptorClass,
                    );

                    if ($filtered === []) {
                        return $this->cache[$interceptorClass] = $this->baseProvider->getPipeline($interceptorClass);
                    }

                    // Use only plugin interceptors - the base pipeline is lost in this edge case.
                    // Users should either use plugins OR a custom PipelineProvider, not both.
                    return $this->cache[$interceptorClass] = Pipeline::prepare($filtered);
                }
            },
        };
    }

    public function getPipeline(string $interceptorClass): Pipeline
    {
        return $this->delegate->getPipeline($interceptorClass);
    }
}
