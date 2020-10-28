<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use JetBrains\PhpStorm\Pure;

interface WorkflowContextInterface extends WorkflowInfoInterface, WorkflowExecutionsInterface
{
    /**
     * @return \DateTimeInterface
     */
    #[Pure]
    public function now(): \DateTimeInterface;

    /**
     * @psalm-return
     * @return int[]
     */
    #[Pure]
    public function getSendRequestIdentifiers(): array;
}
