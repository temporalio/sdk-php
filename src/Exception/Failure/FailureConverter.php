<?php


namespace Temporal\Exception\Failure;


use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;

class FailureConverter
{
    public static function toException(Failure $failure, DataConverterInterface $dataConverter): \Throwable
    {
    }

    public static function toFailure(\Throwable $e, DataConverterInterface $dataConverter): Failure
    {
    }
}
