<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\Api\Enums\V1\RetryState;
use Temporal\Api\Enums\V1\TimeoutType;
use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\Exception\TemporalException;

/**
 * Represents failures that can cross workflow and activity boundaries.
 *
 * <p>Only exceptions that extend this class will be propagated to the caller.
 *
 * <p><b>Never extend this class or any of its derivatives.</b> They are to be used by the SDK code
 * only. Throw an instance {@link ApplicationFailure} to pass application specific errors between
 * workflows and activities.
 *
 * <p>Any unhandled exception thrown by an activity or workflow will be converted to an instance of
 * {@link ApplicationFailure}.
 */
class TemporalFailure extends TemporalException implements \Stringable, SerializationContextAwareInterface
{
    private ?Failure $failure = null;
    private string $originalMessage;
    private ?string $originalStackTrace = null;
    private ?SerializationContext $serializationContext = null;

    public function __construct(string $message, ?string $originalMessage = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->originalMessage = $originalMessage ?? '';
    }

    public function getFailure(): ?Failure
    {
        return $this->failure;
    }

    public function setFailure(?Failure $failure): void
    {
        $this->failure = $failure;
    }

    public function getOriginalMessage(): string
    {
        return $this->originalMessage;
    }

    public function setOriginalStackTrace(string $stackTrace): void
    {
        $this->originalStackTrace = $stackTrace;
        $this->message .= "\nStackTrace:\n" . $this->originalStackTrace;
    }

    /**
     * @psalm-assert-if-true non-empty-string $this->originalStackTrace
     * @psalm-assert-if-false null $this->originalStackTrace
     */
    public function hasOriginalStackTrace(): bool
    {
        return $this->originalStackTrace !== null;
    }

    public function getOriginalStackTrace(): ?string
    {
        return $this->originalStackTrace;
    }

    public function setDataConverter(DataConverterInterface $converter): void
    {
        // typically handled by children
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return $this->serializationContext;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        $this->serializationContext = $context;
        $this->applySerializationContext($context);

        $previous = $this->getPrevious();
        while ($previous !== null) {
            if ($previous instanceof self) {
                $previous->withSerializationContext($context);
                return $this;
            }

            $previous = $previous->getPrevious();
        }

        return $this;
    }

    public function __toString(): string
    {
        if ($this->hasOriginalStackTrace()) {
            return (string) $this->getOriginalStackTrace();
        }

        return parent::__toString();
    }

    /**
     * Explain known types of key=>value pairs.
     */
    protected static function buildMessage(array $values): string
    {
        $mapped = [
            'timeoutType' => static fn($value) => TimeoutType::name($value),
            'timeoutWorkflowType' => static fn($value) => TimeoutType::name($value),
            'retryState' => static fn($value) => RetryState::name($value),
        ];

        $result = [];
        foreach ($values as $key => $value) {
            if (isset($mapped[$key])) {
                $value = ($mapped[$key])($value);
            }

            $result[$key] = $value;
        }

        return parent::buildMessage($result);
    }

    protected function applySerializationContext(?SerializationContext $context): void {}
}
