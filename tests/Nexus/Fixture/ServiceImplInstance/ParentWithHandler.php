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
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;

/**
 * Parent declares the handler; subclass inherits without its own #[OperationImpl].
 * Exercises the inheritance traversal in ServiceImplInstance::collectMethods().
 */
class ParentWithHandler
{
    #[OperationImpl]
    public function operation(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(static fn($ctx, $details, $name) => 'parent');
    }
}
