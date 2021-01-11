<?php


namespace Temporal\Exception\Failure;


use Temporal\DataConverter\EncodedValues;
use Throwable;

class CanceledFailure extends TemporalFailure
{
    /**
     * @var EncodedValues
     */
    private EncodedValues $details;

    /**
     * @param string $message
     * @param EncodedValues $details
     * @param Throwable|null $previous
     */
    public function __construct(string $message, EncodedValues $details, Throwable $previous = null)
    {
        parent::__construct($message, "", $previous);
        $this->details = $details;
    }

    /**
     * @return EncodedValues
     */
    public function getDetails(): EncodedValues
    {
        return $this->details;
    }
}
