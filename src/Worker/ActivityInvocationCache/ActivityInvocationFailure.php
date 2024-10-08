<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

final class ActivityInvocationFailure
{
    /** @var class-string<\Throwable> */
    public string $errorClass;

    public string $errorMessage;

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    public function __construct(
        string $exceptionClass,
        string $exceptionMessage,
    ) {
        $this->errorClass = $exceptionClass;
        $this->errorMessage = $exceptionMessage;
    }

    public static function fromThrowable(\Throwable $error): self
    {
        return new self($error::class, $error->getMessage());
    }

    public function toThrowable(): \Throwable
    {
        return new ($this->errorClass)($this->errorMessage);
    }
}
