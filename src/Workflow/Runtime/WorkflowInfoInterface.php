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

interface WorkflowInfoInterface
{
    /**
     * @return string
     */
    #[Pure]
    public function getName(): string;

    /**
     * @return string
     */
    #[Pure]
    public function getId(): string;

    /**
     * @return string
     */
    #[Pure]
    public function getRunId(): string;

    /**
     * @return string
     */
    #[Pure]
    public function getTaskQueue(): string;

    /**
     * @return array
     */
    #[Pure]
    public function getPayload(): array;
}
