<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception;

use Exception;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Tests\Unit\AbstractUnit;

final class FailureConverterTestCase extends AbstractUnit
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

    public function testShouldSetStackTraceStringForAdditionalContext(): void
    {
        $trace = FailureConverter::mapExceptionToFailure(
            new Exception(),
            DataConverter::createDefault(),
        )->getStackTrace();

        self::assertStringContainsString(
            'Temporal\Tests\Unit\Exception\FailureConverterTestCase->testShouldSetStackTraceStringForAdditionalContext()',
            $trace,
        );

        self::assertStringContainsString(
            'PHPUnit\Framework\TestCase->runTest()',
            $trace,
        );
    }

    public function testShouldSetStackTraceStringForAdditionalContextEvenWhenClassIsNotPresented(): void
    {
        $trace = FailureConverter::mapExceptionToFailure(
            call_user_func(fn () => new Exception()),
            DataConverter::createDefault(),
        )->getStackTrace();

        self::assertStringContainsString(
            'Temporal\Tests\Unit\Exception\FailureConverterTestCase->Temporal\Tests\Unit\Exception\{closure}()',
            $trace,
        );

        self::assertStringContainsString(
            'call_user_func(Closure)',
            $trace,
        );

        self::assertStringContainsString(
            'PHPUnit\Framework\TestCase->runTest()',
            $trace,
        );
    }
}
