<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Internal\Traits\CloneWith;

/**
 * TimerOptions is used to specify options for a timer.
 *
 * @experimental This API is experimental and may change in the future.
 */
final class TimerOptions
{
    use CloneWith;

    public readonly string $summary;

    private function __construct()
    {
        $this->summary = '';
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Single-line fixed summary for this timer that will appear in UI/CLI.
     *
     * This can be in single-line Temporal Markdown format.
     */
    public function withSummary(string $summary): self
    {
        /** @see self::$summary */
        return $this->cloneWith('summary', $summary);
    }
}
