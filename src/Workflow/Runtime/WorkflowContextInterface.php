<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

interface WorkflowContextInterface extends InteractWithQueueInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return string
     */
    public function getRunId(): string;

    /**
     * @return string
     */
    public function getTaskQueue(): string;

    /**
     * @return array
     */
    public function getPayload(): array;

    /**
     * @return \DateTimeInterface
     */
    public function now(): \DateTimeInterface;
}
