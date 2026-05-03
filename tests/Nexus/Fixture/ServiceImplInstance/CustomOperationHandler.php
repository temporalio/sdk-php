<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceImplInstance;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;

/**
 * Custom handler that is NOT a SynchronousOperationHandler — exercises the
 * skip-validation path in {@see \Temporal\Nexus\Handler\ServiceImplInstance}.
 */
final class CustomOperationHandler implements OperationHandlerInterface
{
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        return OperationStartResult::sync('x');
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {}

    public static function sync(callable $function): self
    {
        throw new \LogicException('not a factory handler');
    }
}
