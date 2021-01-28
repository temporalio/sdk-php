<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;

/**
 * Application failure is used to communicate application specific failures between workflows and
 * activities.
 *
 * <p>Throw this exception to have full control over type and details if the exception delivered to
 * the caller workflow or client.
 *
 * <p>Any unhandled exception which doesn't extend {@link TemporalFailure} is converted to an
 * instance of this class before being returned to a caller.
 *
 * <p>The {@code type} property is used by {@link io.temporal.common.RetryOptions} to determine if
 * an instance of this exception is non retryable. Another way to avoid retrying an exception of
 * this type is by setting {@code nonRetryable} flag to @{code true}.
 *
 * <p>The conversion of an exception that doesn't extend {@link TemporalFailure} to an
 * ApplicationFailure is done as following:
 *
 * <ul>
 *   <li>type is set to the exception full type name.
 *   <li>message is set to the exception message
 *   <li>nonRetryable is set to false
 *   <li>details are set to null
 *   <li>stack trace is copied from the original exception
 * </ul>
 */
class ApplicationFailure extends TemporalFailure
{
    private string $type;
    private ValuesInterface $details;
    private bool $nonRetryable;

    /**
     * @param string $message
     * @param string $type
     * @param bool $nonRetryable
     * @param ValuesInterface|null $details
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        string $type,
        bool $nonRetryable,
        ValuesInterface $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(compact('message', 'type', 'nonRetryable')),
            $message,
            $previous
        );

        $this->type = $type;
        $this->nonRetryable = $nonRetryable;
        $this->details = $details ?? EncodedValues::empty();
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return ValuesInterface
     */
    public function getDetails(): ValuesInterface
    {
        return $this->details;
    }

    /**
     * @return bool
     */
    public function isNonRetryable(): bool
    {
        return $this->nonRetryable;
    }

    /**
     * @param bool $nonRetryable
     */
    public function setNonRetryable(bool $nonRetryable): void
    {
        $this->nonRetryable = $nonRetryable;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->details->setDataConverter($converter);
    }
}
