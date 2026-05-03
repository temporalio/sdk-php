<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Function;

use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\SynchronousOperationFunctionInterface;

/**
 * Named functor used as a test fixture for SynchronousOperationHandler::fromFunction().
 *
 * @implements SynchronousOperationFunctionInterface<string, string>
 */
final class UpperCaseFunction implements SynchronousOperationFunctionInterface
{
    public function __invoke(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $input,
    ): mixed {
        return \strtoupper((string) $input);
    }
}
