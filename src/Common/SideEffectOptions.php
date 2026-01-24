<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

use JetBrains\PhpStorm\Pure;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Support\Options;

/**
 * SideEffectOptions provides options for side effects.
 *
 * @psalm-immutable
 */
class SideEffectOptions extends Options
{
    /**
     * Optional summary of the side effect.
     *
     * Single-line fixed summary for this side effect that will appear in UI/CLI.
     * This can be in single-line Temporal Markdown format.
     *
     * @experimental This API is experimental and may change in the future.
     *
     * @since RoadRunner 2025.1.2
     */
    #[Marshal(name: 'Summary')]
    public string $summary = '';

    /**
     * Optional summary of the side effect.
     *
     * Single-line fixed summary for this side effect that will appear in UI/CLI.
     * This can be in single-line Temporal Markdown format.
     *
     * @experimental This API is experimental and may change in the future.
     *
     * @return $this
     */
    #[Pure]
    public function withSummary(string $summary): self
    {
        $self = clone $this;
        $self->summary = $summary;
        return $self;
    }
}
