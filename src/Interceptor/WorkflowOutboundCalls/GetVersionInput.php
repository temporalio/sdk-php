<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;
use Temporal\Workflow\ContinueAsNewOptions;

/**
 * @psalm-immutable
 */
#[Immutable]
final class GetVersionInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public string $changeId,
        #[Immutable]
        public int $minSupported,
        #[Immutable]
        public int $maxSupported,
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
