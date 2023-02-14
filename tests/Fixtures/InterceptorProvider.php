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
use Temporal\Interceptor\Interceptor;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundInterceptor;
use Temporal\Internal\Declaration\Prototype\PrototypeInterface;

final class InterceptorProvider implements \Temporal\Interceptor\InterceptorProvider
{
    /**
     * @template Type of Interceptor
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
    public function __construct(array $classes) {
        // Fill classes list
        foreach ($this->classes as $type => &$list) {
            foreach ($classes as $class) {
                if (\is_a($class, $type, true)) {
                    $list[] = $class;
                }
            }
        }
    }

    public function getInterceptors(PrototypeInterface $prototype, ?string $type = null): array
    {
        // TODO
        // $attributes = $prototype->getClass()->getAttributes();

        $result = [];
        foreach ($this->classes[$type] ?? [] as $class) {
            $result[] = new $class();
        }

        return $result;
    }
}
