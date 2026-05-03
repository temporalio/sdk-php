<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceImplInstance;

use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;

final class NoServiceImplAnnotation
{
    public function operation(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(static fn($ctx, $details, $name) => '');
    }
}
