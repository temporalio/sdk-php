<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Validation\OperationTokenValidator;

/**
 * Identifies an operation by its token together with its current state.
 *
 * Wire schema per Nexus SPEC.md §OperationInfo:
 * https://github.com/nexus-rpc/api/blob/main/SPEC.md
 */
final class OperationInfo
{
    public function __construct(
        public readonly string $token,
        public readonly OperationState $state,
    ) {
        OperationTokenValidator::assert($token);
    }
}
