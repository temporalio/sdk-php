<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Fixtures;

use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundInterceptor;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Interceptor\Pipeline;

final class PipelineProvider implements \Temporal\Internal\Interceptor\PipelineProvider
{
    /**
     * @template Type of Interceptor
     *
     * @param array<class-string<Type>, array<class-string<Type>>> $classes
     */
    private array $classes = [
        WorkflowInboundInterceptor::class => [],
        WorkflowOutboundInterceptor::class => [],
        ActivityInboundInterceptor::class => [],
    ];

    /**
     * @param array<class-string<Interceptor>> $classes
     */
    public function __construct(array $classes)
    {
        // Fill classes list
        foreach ($this->classes as $type => &$list) {
            foreach ($classes as $class) {
                if (\is_a($class, $type, true)) {
                    $list[] = $class;
                }
            }
        }
    }

    public function getPipeline(string $interceptorClass): Pipeline
    {
        $result = [];
        foreach ($this->classes[$interceptorClass] ?? [] as $class) {
            $result[] = new $class();
        }

        return Pipeline::prepare($result);
    }
}
