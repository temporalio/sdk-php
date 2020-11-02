<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use JetBrains\PhpStorm\Pure;

interface WorkflowContextInterface extends WorkflowExecutionsInterface
{
    /**
     * @return WorkflowInfo
     */
    public function getInfo(): WorkflowInfo;

    /**
     * @return array
     */
    public function getArguments(): array;

    /**
     * @return \DateTimeInterface
     */
    #[Pure]
    public function now(): \DateTimeInterface;

    /**
     * @return int[]
     */
    #[Pure]
    public function getSendRequestIdentifiers(): array;

    /**
     * @return bool
     */
    public function isReplaying(): bool;
}
