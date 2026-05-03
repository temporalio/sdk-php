<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceHandler;

use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Tests\Nexus\Fixture\Service\IntServiceInterface;

#[ServiceImpl(service: IntServiceInterface::class)]
final class IntServiceImpl
{
    #[OperationImpl]
    public function operation(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(static fn($ctx, $details, $input) => 0);
    }
}
