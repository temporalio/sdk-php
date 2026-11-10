<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter\ExternalStorage;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\DataConverter\ExternalStorage\StorageDriverSelectContext;
use Temporal\Tests\Unit\Internal\ExternalStorage\Stub\InMemoryStorageDriver;

final class ExternalStorageTestCase extends TestCase
{
    public function testSingleDriverIsSelectedByDefault(): void
    {
        $driver = new InMemoryStorageDriver();

        $options = new ExternalStorage([$driver]);

        self::assertSame(['memory' => $driver], $options->drivers);
        self::assertSame($driver, ($options->driverSelector)(new StorageDriverSelectContext(null, 'default'), new Payload()));
        self::assertSame(ExternalStorage::DEFAULT_PAYLOAD_SIZE_THRESHOLD, $options->payloadSizeThreshold);
        self::assertSame(8, $options->maxOperationsPerMessage);
        self::assertSame(64, $options->maxDriverOperations);
    }

    public function testEmptyDriverNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A storage driver name must not be empty.');

        new ExternalStorage([new InMemoryStorageDriver('')]);
    }

    public function testBudgetsMustBePositive(): void
    {
        foreach (['maxOperationsPerMessage', 'maxDriverOperations'] as $budget) {
            try {
                new ExternalStorage([new InMemoryStorageDriver()], ...[$budget => 0]);
                self::fail("A zero `$budget` is accepted.");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($budget, $e->getMessage());
            }
        }
    }

    public function testDriversAreRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExternalStorage([]);
    }

    public function testNegativeThresholdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExternalStorage([new InMemoryStorageDriver()], payloadSizeThreshold: -1);
    }

    public function testSelectorIsRequiredForSeveralDrivers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExternalStorage([new InMemoryStorageDriver('a'), new InMemoryStorageDriver('b')]);
    }

    public function testDriverNamesMustBeUnique(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Multiple storage drivers registered with name 'a'.");

        new ExternalStorage(
            [new InMemoryStorageDriver('a'), new InMemoryStorageDriver('a')],
            static fn() => null,
        );
    }
}
