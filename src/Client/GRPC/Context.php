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
use Temporal\Client\Common\RpcRetryOption;
use Temporal\Common\RetryOptions;
use Temporal\Common\SdkVersion;
use Temporal\Internal\Support\DateInterval;

final class Context implements ContextInterface
{
    private ?\DateTimeInterface $deadline = null;
    private array $options = [];
    private array $metadata;
    private RpcRetryOption $retryOptions;

    private function __construct()
    {
        $this->retryOptions = RpcRetryOption::new()
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
        $ctx->deadline = (new \DateTimeImmutable())->add($internal);

        return $ctx;
    }

    public function withDeadline(\DateTimeInterface $deadline): self
    {
        $ctx = clone $this;
        $ctx->deadline = $deadline;

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
        $ctx->retryOptions = $options instanceof RpcRetryOption ? $options : RpcRetryOption::fromRetryOptions($options);

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
        return $this->deadline;
    }

    public function getRetryOptions(): RpcRetryOption
    {
        return $this->retryOptions;
    }

    public static function default(): self
    {
        return new self();
    }
}
