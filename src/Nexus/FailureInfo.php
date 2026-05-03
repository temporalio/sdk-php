<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

/**
 * Information about an operation failure.
 */
final class FailureInfo implements \Stringable
{
    private const STACK_TRACE_PREVIEW = 120;

    /**
     * @param array<string, string> $metadata
     * @param FailureInfo|null $cause Nested underlying failure per Nexus spec §Failure.
     */
    public function __construct(
        public readonly string $message,
        public readonly ?string $stackTrace = null,
        public readonly array $metadata = [],
        public readonly ?string $detailsJson = null,
        public readonly ?FailureInfo $cause = null,
    ) {}

    /**
     * Walk `getPrevious()` chain up to $maxDepth levels; deeper causes are truncated.
     */
    public static function fromThrowable(\Throwable $e, int $maxDepth = 10): self
    {
        $previous = $e->getPrevious();

        return new self(
            message: $e->getMessage(),
            stackTrace: $e->getTraceAsString(),
            cause: $maxDepth > 0 && $previous !== null
                ? self::fromThrowable($previous, $maxDepth - 1)
                : null,
        );
    }

    public function __toString(): string
    {
        $parts = [
            "message='{$this->message}'",
        ];
        if ($this->stackTrace !== null) {
            $parts[] = 'stackTrace=' . \substr($this->stackTrace, 0, self::STACK_TRACE_PREVIEW)
                . (\strlen($this->stackTrace) > self::STACK_TRACE_PREVIEW ? '…' : '');
        }
        if ($this->metadata !== []) {
            $parts[] = 'metadata=' . \json_encode($this->metadata);
        }
        if ($this->detailsJson !== null) {
            $parts[] = 'details=' . $this->detailsJson;
        }
        if ($this->cause !== null) {
            $parts[] = 'cause=' . (string) $this->cause;
        }

        return 'FailureInfo{' . \implode(', ', $parts) . '}';
    }
}
