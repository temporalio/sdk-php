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
use Temporal\Client\Common\RpcRetryOptions;
use Temporal\Common\RetryOptions;
use Temporal\Common\SdkVersion;
use Temporal\Internal\Support\DateInterval;

final class Context implements ContextInterface
{
    private ?\DateTimeInterface $deadline = null;
    private ?\DateInterval $timeout = null;
    private array $options = [];
    private array $metadata;
    private RetryOptions $retryOptions;

    private function __construct()
    {
        $this->retryOptions = RpcRetryOptions::new()
            ->withMaximumAttempts(0)
            ->withInitialInterval(CarbonInterval::millisecond(500));

        $this->metadata = [
            'client-name' => ['temporal-php-2'],
            'client-version' => [SdkVersion::getSdkVersion()],
        ];
    }

    public function withTimeout($timeout, string $format = DateInterval::FORMAT_SECONDS): self
    {
        $internal = DateInterval::parse($timeout, $format);

        $ctx = clone $this;
        $ctx->timeout = $internal;
        $ctx->deadline = null;

        return $ctx;
    }

    public function withDeadline(\DateTimeInterface $deadline): self
    {
        $ctx = clone $this;
        $ctx->deadline = $deadline;
        $ctx->timeout = null;

        return $ctx;
    }

    public function withOptions(array $options): self
    {
        $ctx = clone $this;
        $ctx->options = $options;

        return $ctx;
    }

    public function withMetadata(array $metadata): self
    {
        $ctx = clone $this;
        $ctx->metadata = $metadata;

        return $ctx;
    }

    public function withRetryOptions(RetryOptions $options): ContextInterface
    {
        $ctx = clone $this;
        $ctx->retryOptions = $options;

        return $ctx;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return match (true) {
            $this->deadline !== null => $this->deadline,
            $this->timeout !== null => (new \DateTime())->add($this->timeout),
            default => null,
        };
    }

    public function getRetryOptions(): RetryOptions
    {
        return $this->retryOptions;
    }

    public static function default(): self
    {
        return new self();
    }
}
