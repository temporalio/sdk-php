<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Carbon\CarbonInterval;
use Composer\InstalledVersions;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Support\DateInterval;

final class Context implements ContextInterface
{
    private ?\DateTimeInterface $deadline = null;
    private array $options;
    private array $metadata = [];
    private RetryOptions $retryOptions;

    /**
     * Context constructor.
     */
    private function __construct()
    {
        $this->retryOptions = RetryOptions::new()
            ->withMaximumAttempts(0)
            ->withInitialInterval(CarbonInterval::millisecond(500));

        $this->options = [
            'client-name' => 'temporal-php',
            'client-version' => InstalledVersions::getVersion('temporal/sdk'),
        ];
    }

    /**
     * @param DateInterval|int $timeout
     * @param string $format
     * @return $this
     */
    public function withTimeout($timeout, string $format = DateInterval::FORMAT_SECONDS): self
    {
        $internal = DateInterval::parse($timeout, $format);

        $ctx = clone $this;
        $ctx->deadline = (new \DateTimeImmutable())->add($internal);

        return $ctx;
    }

    /**
     * @param \DateTimeInterface $deadline
     * @return $this
     */
    public function withDeadline(\DateTimeInterface $deadline): self
    {
        $ctx = clone $this;
        $ctx->deadline = $deadline;

        return $ctx;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options): self
    {
        $ctx = clone $this;
        $ctx->options = $options;

        return $ctx;
    }

    /**
     * @param array $metadata
     * @return $this
     */
    public function withMetadata(array $metadata): self
    {
        $ctx = clone $this;
        $ctx->metadata = $metadata;

        return $ctx;
    }

    /**
     * @param RetryOptions $options
     * @return ContextInterface
     */
    public function withRetryOptions(RetryOptions $options): ContextInterface
    {
        $ctx = clone $this;
        $ctx->retryOptions = $options;

        return $ctx;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    /**
     * @return RetryOptions
     */
    public function getRetryOptions(): RetryOptions
    {
        return $this->retryOptions;
    }

    /**
     * @return Context
     */
    public static function default()
    {
        return new self();
    }
}
