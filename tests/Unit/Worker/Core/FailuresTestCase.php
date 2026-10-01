<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\DataConverterException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Worker\Core\Failures;

final class FailuresTestCase extends TestCase
{
    public function testInvalidUtf8MessageFallsBackToScrubbedFailure(): void
    {
        $failure = Failures::fromThrowable(new \RuntimeException("bad \xff byte"), DataConverter::createDefault());

        self::assertSame('bad ? byte', $failure->getMessage());
        self::assertSame(FailureConverter::SOURCE, $failure->getSource());
        self::assertSame(\RuntimeException::class, $failure->getApplicationFailureInfo()->getType());
    }

    public function testConverterErrorsAreNotHidden(): void
    {
        $resource = \fopen('php://memory', 'rb');
        $error = new ApplicationFailure('message', 'type', false, EncodedValues::fromValues([$resource]));

        try {
            $this->expectException(DataConverterException::class);
            Failures::fromThrowable($error, DataConverter::createDefault());
        } finally {
            \fclose($resource);
        }
    }
}
