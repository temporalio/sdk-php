<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Interceptor;

use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Interceptor\GrpcClientInterceptor;

/**
 * Calls `GetSystemInfo` on the first invocation of a client method on the current connection to the Temporal service.
 */
final class SystemInfoInterceptor implements GrpcClientInterceptor
{
    public function __construct(
        private readonly ServiceClientInterface $serviceClient,
    ) {}

    /**
     * @param non-empty-string $method
     * @param callable(non-empty-string, object, ContextInterface): object $next
     */
    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
    {
        $this->serviceClient->getServerCapabilities();

        return $next($method, $arg, $ctx);
    }
}
