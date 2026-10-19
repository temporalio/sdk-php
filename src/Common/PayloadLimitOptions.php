<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class PayloadLimitOptions
{
    public const DEFAULT_PAYLOAD_SIZE_WARNING = 512 * 1024;
    public const DEFAULT_MEMO_SIZE_WARNING = 2 * 1024;

    /**
     * @param null|positive-int $payloadSizeWarning
     * @param null|positive-int $memoSizeWarning
     */
    public function __construct(
        public readonly ?int $payloadSizeWarning = self::DEFAULT_PAYLOAD_SIZE_WARNING,
        public readonly ?int $memoSizeWarning = self::DEFAULT_MEMO_SIZE_WARNING,
    ) {
        self::assertPositive($payloadSizeWarning, 'payloadSizeWarning');
        self::assertPositive($memoSizeWarning, 'memoSizeWarning');
    }

    /**
     * @experimental This API is experimental and may change in the future.
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * @experimental This API is experimental and may change in the future.
     */
    public static function disabled(): self
    {
        return new self(null, null);
    }

    /**
     * @param null|positive-int $bytes
     *
     * @experimental This API is experimental and may change in the future.
     */
    public function withPayloadSizeWarning(?int $bytes): self
    {
        return new self($bytes, $this->memoSizeWarning);
    }

    /**
     * @param null|positive-int $bytes
     *
     * @experimental This API is experimental and may change in the future.
     */
    public function withMemoSizeWarning(?int $bytes): self
    {
        return new self($this->payloadSizeWarning, $bytes);
    }

    /**
     * @experimental This API is experimental and may change in the future.
     */
    public function isEnabled(): bool
    {
        return $this->payloadSizeWarning !== null || $this->memoSizeWarning !== null;
    }

    private static function assertPositive(?int $value, string $name): void
    {
        if ($value !== null && $value <= 0) {
            throw new \InvalidArgumentException(
                "`$name` must be a positive number of bytes or NULL to disable the warning.",
            );
        }
    }
}
