<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Impl;

use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Tests\Nexus\Fixture\Service\GreetingServiceInterface;

/**
 * Greeting-service implementation whose operation factories return either an
 * injected handler override (for testing exception paths) or a benign sync
 * stub returning `'ok'`.
 */
#[ServiceImpl(service: GreetingServiceInterface::class)]
final class ThrowingGreetingImpl
{
    /**
     * @param OperationHandlerInterface<string, string>|null $hello1Override
     * @param OperationHandlerInterface<string, string>|null $hello2Override
     */
    public function __construct(
        private readonly ?OperationHandlerInterface $hello1Override = null,
        private readonly ?OperationHandlerInterface $hello2Override = null,
    ) {}

    #[OperationImpl]
    public function sayHello1(): OperationHandlerInterface
    {
        return $this->hello1Override
            ?? SynchronousOperationHandler::fromCallable(static fn($ctx, $d, $p) => 'ok');
    }

    #[OperationImpl]
    public function sayHello2(): OperationHandlerInterface
    {
        return $this->hello2Override
            ?? SynchronousOperationHandler::fromCallable(static fn($ctx, $d, $p) => 'ok');
    }
}
