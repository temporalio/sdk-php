<?php

namespace Temporal\Exception\Failure;

use Temporal\Api\Failure\V1\Failure;
use Temporal\Exception\TemporalException;
use Throwable;

class TemporalFailure extends TemporalException
{
    /**
     * @var Failure|null
     */
    private ?Failure $failure;

    /**
     * @var string
     */
    private string $originalMessage;

    /**
     * @param string $message
     * @param string|null $originalMessage
     * @param Throwable|null $previous
     */
    public function __construct(string $message, string $originalMessage = null, Throwable $previous = null)
    {
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
}
