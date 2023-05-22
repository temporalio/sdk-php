<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Client\GRPC\ContextInterface;
use Temporal\Internal\Interceptor\Interceptor;

/**
 * Interceptor intercepts client gRPC calls.
 *
 * @psalm-immutable
 */
interface GrpcClientInterceptor extends Interceptor
{
    /**
     * Intercepts a gRPC call.
     *
     * @param non-empty-string $method
     * @param object $arg
     * @param ContextInterface $ctx
     * @param callable(non-empty-string, object, ContextInterface): object $next
     *
     * @return object
     */
    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object;
}
