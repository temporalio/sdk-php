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

/**
 * @psalm-import-type  DateIntervalValue from DateInterval
 */
interface ContextInterface
{
    /**
     * @param DateIntervalValue $timeout
     * @return $this
     */
    public function withTimeout($timeout, string $format = DateInterval::FORMAT_SECONDS): ContextInterface;

    /**
     * @return $this
     */
    public function withDeadline(\DateTimeInterface $deadline): ContextInterface;

    public function withOptions(array $options): ContextInterface;

    public function withMetadata(array $metadata): ContextInterface;

    public function withRetryOptions(RetryOptions $options): ContextInterface;

    public function getOptions(): array;

    public function getMetadata(): array;

    public function getDeadline(): ?\DateTimeInterface;

    public function getRetryOptions(): RetryOptions;
}
