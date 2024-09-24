<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception;

use Carbon\CarbonInterval;
use Exception;
use Google\Protobuf\Duration;
use Temporal\Api\Failure\V1\Failure;
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
            EncodedValues::fromValues(['abc', 123]),
        );

        $failure = FailureConverter::mapExceptionToFailure($exception, DataConverter::createDefault());
        $restoredDetails = EncodedValues::fromPayloads(
            $failure->getApplicationFailureInfo()->getDetails(),
            DataConverter::createDefault(),
        );

        $this->assertSame('abc', $restoredDetails->getValue(0));
        $this->assertSame(123, $restoredDetails->getValue(1));
    }

    public function testStackTraceStringForAdditionalContext(): void
    {
        $trace = FailureConverter::mapExceptionToFailure(
            new Exception(),
            DataConverter::createDefault(),
        )->getStackTrace();

        self::assertStringContainsString(
            'Temporal\Tests\Unit\Exception\FailureConverterTestCase->testStackTraceStringForAdditionalContext()',
            $trace,
        );

        self::assertStringContainsString(
            'PHPUnit\Framework\TestCase->runTest()',
            $trace,
        );
    }

    public function testStackTraceStringForAdditionalContextEvenWhenClassIsNotPresented(): void
    {
        $previous = \ini_get('zend.exception_ignore_args');
        \ini_set('zend.exception_ignore_args', 'Off');

        try {
            $trace = FailureConverter::mapExceptionToFailure(
                call_user_func(fn() => new Exception()),
                DataConverter::createDefault(),
            )->getStackTrace();
        } finally {
            \ini_set('zend.exception_ignore_args', $previous);
        }

        self::assertStringContainsString(
            '[internal function]',
            $trace,
        );

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

    public function testStackTraceStringWithoutExceptionArgs(): void
    {
        $previous = \ini_get('zend.exception_ignore_args');
        \ini_set('zend.exception_ignore_args', 'On');

        try {
            $trace = FailureConverter::mapExceptionToFailure(
                call_user_func(static fn() => new Exception()),
                DataConverter::createDefault(),
            )->getStackTrace();
        } finally {
            \ini_set('zend.exception_ignore_args', $previous);
        }

        self::assertStringContainsString(
            'call_user_func()',
            $trace,
        );
    }

    public function testMapFailureToException(): void
    {
        $converter = DataConverter::createDefault();
        $failure = new Failure();
        $failure->setApplicationFailureInfo($info = new \Temporal\Api\Failure\V1\ApplicationFailureInfo());
        $failure->setStackTrace("test stack trace:\n#1\n#2\n#3");
        // Populate the info
        $info->setType('testType');
        $info->setDetails(EncodedValues::fromValues(['foo', 'bar'], $converter)->toPayloads());
        $info->setNonRetryable(true);
        $info->setNextRetryDelay((new Duration())->setSeconds(13)->setNanos(15_000));

        $exception = FailureConverter::mapFailureToException($failure, $converter);

        $this->assertInstanceOf(ApplicationFailure::class, $exception);
        $this->assertSame('testType', $exception->getType());
        $this->assertTrue($exception->isNonRetryable());
        $this->assertSame(['foo', 'bar'], $exception->getDetails()->getValues());
        // Next retry delay
        $this->assertSame(13, $exception->getNextRetryDelay()->seconds);
        $this->assertSame(15, $exception->getNextRetryDelay()->microseconds);
        $this->assertTrue($exception->hasOriginalStackTrace());
        $this->assertSame("test stack trace:\n#1\n#2\n#3", $exception->getOriginalStackTrace());
    }

    public function testMapExceptionToFailureWithNextRetryDelay(): void
    {
        $converter = DataConverter::createDefault();
        $exception = new ApplicationFailure(
            'message',
            'type',
            true,
            EncodedValues::fromValues(['foo', 'bar'], $converter),
            nextRetryDelay: CarbonInterval::fromString('5 minutes 13 seconds 15 microseconds'),
        );

        $failure = FailureConverter::mapExceptionToFailure($exception, $converter);

        $this->assertSame('type', $failure->getApplicationFailureInfo()->getType());
        $this->assertTrue($failure->getApplicationFailureInfo()->getNonRetryable());
        $this->assertSame(['foo', 'bar'], EncodedValues::fromPayloads(
            $failure->getApplicationFailureInfo()->getDetails(),
            $converter,
        )->getValues());
        $this->assertSame(5 * 60 + 13, $failure->getApplicationFailureInfo()->getNextRetryDelay()->getSeconds());
        $this->assertSame(15_000, $failure->getApplicationFailureInfo()->getNextRetryDelay()->getNanos());
    }

    public function testMapExceptionToFailure(): void
    {
        $converter = DataConverter::createDefault();
        $exception = new ApplicationFailure(
            'message',
            'type',
            true,
        );

        $failure = FailureConverter::mapExceptionToFailure($exception, $converter);

        $this->assertSame('type', $failure->getApplicationFailureInfo()->getType());
        $this->assertTrue($failure->getApplicationFailureInfo()->getNonRetryable());
        $this->assertEmpty($failure->getApplicationFailureInfo()->getDetails());
        $this->assertNull($failure->getApplicationFailureInfo()->getNextRetryDelay());
    }
}
