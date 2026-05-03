<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Validation\OperationTokenValidator;

/**
 * Details for handling operation cancel.
 */
final class OperationCancelDetails
{
    public function __construct(
        public readonly string $operationToken,
    ) {
        OperationTokenValidator::assert($operationToken);
    }
}
