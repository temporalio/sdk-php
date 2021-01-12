<?php

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
class TemporalFailure extends TemporalException
{
    private ?Failure $failure = null;
    private string $originalMessage;

    /**
     * @param string $message
     * @param string|null $originalMessage
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        string $originalMessage = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->originalMessage = $originalMessage ?? '';
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

    public function setOriginalStackTrace(string $stackTrace)
    {
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
            'timeoutType' => fn($value) => TimeoutType::name($value),
            'retryState' => fn($value) => RetryState::name($value)
        ];

        $mapped = [];
        foreach ($values as $key => $value) {
            if (isset($mapped[$key])) {
                $values = ($mapped[$key])($value);
            }

            $mapped[$key] = $value;
        }

        return parent::buildMessage($mapped);
    }

    // todo: support external trace as string
}
