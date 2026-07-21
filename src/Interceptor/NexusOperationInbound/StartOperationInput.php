<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\NexusOperationInbound;

use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;

/**
 * @psalm-immutable
 */
final class StartOperationInput
{
    private const UNSET = "\0__temporal_unset__\0";

    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly OperationContext $operationContext,
        public readonly OperationStartDetails $startDetails,
        public readonly mixed $input,
    ) {}

    public function with(
        ?OperationContext $operationContext = null,
        ?OperationStartDetails $startDetails = null,
        mixed $input = self::UNSET,
    ): self {
        return new self(
            $operationContext ?? $this->operationContext,
            $startDetails ?? $this->startDetails,
            $input === self::UNSET ? $this->input : $input,
        );
    }
}
