<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\NexusOperationInbound;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;

/**
 * @psalm-immutable
 */
final class NexusOperationCancelInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly OperationContext $context,
        public readonly OperationCancelDetails $details,
    ) {}

    public function with(
        ?OperationContext $context = null,
        ?OperationCancelDetails $details = null,
    ): self {
        return new self(
            $context ?? $this->context,
            $details ?? $this->details,
        );
    }
}
