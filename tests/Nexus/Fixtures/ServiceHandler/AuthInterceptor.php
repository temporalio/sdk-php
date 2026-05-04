<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceHandler;

use Temporal\Interceptor\NexusOperationInbound\NexusOperationCancelInput;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationStartInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationStartResult;

/**
 * Rejects operations missing the expected auth token. Short-circuits before
 * the underlying handler is invoked.
 */
final class AuthInterceptor implements NexusOperationInboundCallsInterceptor
{
    public const AUTH_HEADER = 'authorization';

    public function __construct(
        private readonly string $authToken,
    ) {}

    public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult
    {
        $this->assertAuthorized($input->context->headers[self::AUTH_HEADER] ?? null);
        return $next($input);
    }

    public function cancelNexusOperation(NexusOperationCancelInput $input, callable $next): void
    {
        $this->assertAuthorized($input->context->headers[self::AUTH_HEADER] ?? null);
        $next($input);
    }

    private function assertAuthorized(?string $token): void
    {
        if ($token !== $this->authToken) {
            throw HandlerException::create(ErrorType::Unauthorized, 'Unauthorized');
        }
    }
}
