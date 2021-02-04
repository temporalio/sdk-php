<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception;

class CompensationException extends TemporalException
{
    /**
     * @var array<\Throwable>
     */
    private array $suppressed = [];

    /**
     * @param \Throwable $e
     */
    public function addSuppressed(\Throwable $e): void
    {
        $this->suppressed[] = $e;
    }

    /**
     * @return \Throwable[]
     */
    public function getSuppressed(): array
    {
        return $this->suppressed;
    }
}
