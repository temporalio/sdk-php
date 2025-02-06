<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use Temporal\Common\SearchAttributes\SearchAttributeUpdate;

/**
 * @psalm-immutable
 */
final class UpsertTypedSearchAttributesInput
{
    /**
     * @param array<SearchAttributeUpdate> $updates
     *
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly array $updates,
    ) {}

    /**
     * @param array<SearchAttributeUpdate>|null $updates
     */
    public function with(
        ?array $updates = null,
    ): self {
        return new self(
            $updates ?? $this->updates,
        );
    }
}
