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
class TemporalFailure extends TemporalException implements \Stringable
{
    private ?Failure $failure = null;
    private string $originalMessage;
    private ?string $originalStackTrace = null;

    /**
     * @param string $message
     * @param string|null $originalMessage
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, string $originalMessage = null, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->originalMessage = $originalMessage ?? '';
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if ($this->hasOriginalStackTrace()) {
            return (string)$this->getOriginalStackTrace();
        }

        return parent::__toString();
    }

    /**
     * @return Failure|null
     */
    public function getFailure(): ?Failure
    {
        return $this->failure;
    }

    /**
     * @param Failure|null $failure
     */
    public function setFailure(?Failure $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @return string
     */
    public function getOriginalMessage(): string
    {
        return $this->originalMessage;
    }

    /**
     * @param string $stackTrace
     */
    public function setOriginalStackTrace(string $stackTrace): void
    {
        $this->originalStackTrace = $stackTrace;
        $this->message .= "\nStackTrace:\n" . $this->originalStackTrace;
    }

    /**
     * @return bool
     */
    public function hasOriginalStackTrace(): bool
    {
        return $this->originalStackTrace !== null;
    }

    /**
     * @return string|null
     */
    public function getOriginalStackTrace(): ?string
    {
        return $this->originalStackTrace;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        // typically handled by children
    }

    /**
     * Explain known types of key=>value pairs.
     *
     * @param array $values
     * @return string
     */
    protected static function buildMessage(array $values): string
    {
        $mapped = [
            'timeoutType' => fn ($value) => TimeoutType::name($value),
            'timeoutWorkflowType' => fn ($value) => TimeoutType::name($value),
            'retryState' => fn ($value) => RetryState::name($value),
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
}
