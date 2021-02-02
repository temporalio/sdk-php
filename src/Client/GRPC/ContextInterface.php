<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Common\RetryOptions;
use Temporal\Internal\Support\DateInterval;

interface ContextInterface
{
    /**
     * @param DateInterval|int $timeout
     * @param string $format
     * @return $this
     */
    public function withTimeout($timeout, string $format = DateInterval::FORMAT_SECONDS): ContextInterface;

    /**
     * @param \DateTimeInterface $deadline
     * @return $this
     */
    public function withDeadline(\DateTimeInterface $deadline): ContextInterface;

    /**
     * @param array $options
     * @return ContextInterface
     */
    public function withOptions(array $options): ContextInterface;

    /**
     * @param array $metadata
     * @return ContextInterface
     */
    public function withMetadata(array $metadata): ContextInterface;

    /**
     * @param RetryOptions $options
     * @return ContextInterface
     */
    public function withRetryOptions(RetryOptions $options): ContextInterface;

    /**
     * @return array
     */
    public function getOptions(): array;

    /**
     * @return array
     */
    public function getMetadata(): array;

    /**
     * @return \DateTimeInterface|null
     */
    public function getDeadline(): ?\DateTimeInterface;

    /**
     * @return RetryOptions
     */
    public function getRetryOptions(): RetryOptions;
}
