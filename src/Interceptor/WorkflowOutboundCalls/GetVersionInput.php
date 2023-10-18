<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

/**
 * @psalm-immutable
 */
final class GetVersionInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $changeId,
        public readonly int $minSupported,
        public readonly int $maxSupported,
    ) {
    }

    public function with(
        ?string $changeId = null,
        ?int $minSupported = null,
        ?int $maxSupported = null,
    ): self {
        return new self(
            $changeId ?? $this->changeId,
            $minSupported ?? $this->minSupported,
            $maxSupported ?? $this->maxSupported,
        );
    }
}
