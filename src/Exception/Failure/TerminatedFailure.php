<?php


namespace Temporal\Exception\Failure;


use Throwable;

class TerminatedFailure extends TemporalFailure
{
    /**
     * @param string $message
     * @param Throwable|null $previous
     */
    public function __construct(string $message, Throwable $previous = null)
    {
        parent::__construct($message, $message, $previous);
    }
}
