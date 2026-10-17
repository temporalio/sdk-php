<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

/**
 * Workflow-id conflict policy options: what to attach to the existing run when
 * a start request conflicts. Serialized to {@see \Temporal\Api\Workflow\V1\OnConflictOptions}.
 *
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
}
