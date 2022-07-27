<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Tests\Unit\UnitTestCase;

final class FailureConverterTestCase extends UnitTestCase
{
    public function testApplicationFailureCanTransferData(): void
    {
        $exception = new ApplicationFailure(
            'message',
            'type',
            true,
            EncodedValues::fromValues(['abc', 123])
        );

        $failure = FailureConverter::mapExceptionToFailure($exception, DataConverter::createDefault());
        $restoredDetails = EncodedValues::fromPayloads(
            $failure->getApplicationFailureInfo()->getDetails(),
            DataConverter::createDefault()
        );

        $this->assertSame('abc', $restoredDetails->getValue(0));
        $this->assertSame(123, $restoredDetails->getValue(1));

    }
}
