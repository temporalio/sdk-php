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
final class CancelOperationInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly OperationContext $operationContext,
        public readonly OperationCancelDetails $cancelDetails,
    ) {}

    public function with(
        ?OperationContext $operationContext = null,
        ?OperationCancelDetails $cancelDetails = null,
    ): self {
        return new self(
            $operationContext ?? $this->operationContext,
            $cancelDetails ?? $this->cancelDetails,
        );
    }
}
