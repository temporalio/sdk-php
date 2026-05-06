<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

/**
 * @internal
 * @psalm-immutable
 */
final class OnConflictOptions
{
    public function __construct(
        public readonly bool $attachRequestId = true,
        public readonly bool $attachCompletionCallbacks = true,
        public readonly bool $attachLinks = true,
    ) {}

    /**
     * Canonical value used by the Nexus caller-side path so retried
     * StartWorkflow requests attach the new callback to the existing run.
     */
    public static function forNexusCompletionCallback(): self
    {
        return new self(
            attachRequestId: true,
            attachCompletionCallbacks: true,
            attachLinks: true,
        );
    }
}
