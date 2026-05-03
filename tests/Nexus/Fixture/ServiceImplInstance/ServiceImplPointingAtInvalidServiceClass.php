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

#[ServiceImpl(service: 'NonExistent\\Class\\That\\Does\\Not\\Exist')]
final class ServiceImplPointingAtInvalidServiceClass
{
    #[OperationImpl]
    public function op(): OperationHandlerInterface
    {
        return SynchronousOperationHandler::fromCallable(static fn() => null);
    }
}
