<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceImplInstance;

use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Tests\Nexus\Fixture\Function\UpperCaseFunction;
use Temporal\Tests\Nexus\Fixture\Service\GreetingServiceInterface;

#[ServiceImpl(service: GreetingServiceInterface::class)]
final class ServiceImplWithFunctorHandler
{
    #[OperationImpl]
    public function sayHello1(): OperationHandlerInterface
    {
        return SynchronousOperationHandler::fromFunction(new UpperCaseFunction());
    }

    #[OperationImpl]
    public function sayHello2(): OperationHandlerInterface
    {
        return SynchronousOperationHandler::fromFunction(new UpperCaseFunction());
    }
}
