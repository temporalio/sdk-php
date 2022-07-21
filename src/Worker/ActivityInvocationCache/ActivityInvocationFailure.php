<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use Throwable;

final class ActivityInvocationFailure
{
    public string $errorClass;
    public string $errorMessage;

    public function __construct(string $exceptionClass, string $exceptionMessage)
    {
        $this->errorClass = $exceptionClass;
        $this->errorMessage = $exceptionMessage;
    }

    public static function fromThrowable(Throwable $error): self
    {
        return new self(get_class($error), $error->getMessage());
    }

    public function toThrowable(): Throwable
    {
        $errorClass = $this->errorClass;

        return new $errorClass($this->errorMessage);
    }
}
