<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceHandler;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationMiddlewareInterface;

/**
 * Rejects operations missing the expected auth token. Short-circuits in `intercept()`.
 */
final class AuthInterceptor implements OperationMiddlewareInterface
{
    public const AUTH_HEADER = 'authorization';

    public function __construct(
        private readonly string $authToken,
    ) {}

    public function intercept(
        OperationContext $context,
        OperationHandlerInterface $next,
    ): OperationHandlerInterface {
        if (($context->headers[self::AUTH_HEADER] ?? null) !== $this->authToken) {
            throw HandlerException::create(ErrorType::Unauthorized, 'Unauthorized');
        }
        return $next;
    }
}
