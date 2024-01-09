<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Interceptor;

use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\ServerCapabilities;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Interceptor\GrpcClientInterceptor;

/**
 * @psalm-immutable
 */
final class SystemInfoInterceptor implements GrpcClientInterceptor
{
    private bool $systemInfoRequested = false;

    public function __construct(
        private readonly ServiceClient $serviceClient
    ) {
    }

    /**
     * @param non-empty-string $method
     * @param callable(non-empty-string, object, ContextInterface): object $next
     */
    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
    {
        if ($this->systemInfoRequested) {
            return $next($method, $arg, $ctx);
        }

        try {
            $systemInfo = $this->serviceClient->getSystemInfo(new GetSystemInfoRequest());

            $capabilities = $systemInfo->getCapabilities();
            if ($capabilities !== null && $this->serviceClient->getServerCapabilities() === null) {
                $this->serviceClient->setServerCapabilities(new ServerCapabilities(
                    signalAndQueryHeader: $capabilities->getSignalAndQueryHeader(),
                    internalErrorDifferentiation: $capabilities->getInternalErrorDifferentiation(),
                ));
            }
        } catch (ServiceClientException $e) {
            if ($e->getCode() !== StatusCode::UNIMPLEMENTED) {
                throw $e;
            }
        }

        $this->systemInfoRequested = true;

        return $next($method, $arg, $ctx);
    }
}
