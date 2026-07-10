<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Tests\Unit\DTO\AbstractDTOMarshalling;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusOperationOptions::class)]
final class NexusOperationOptionsTestCase extends AbstractDTOMarshalling
{
    public function testNewHasEmptyDefaults(): void
    {
        $options = NexusOperationOptions::new();

        self::assertSame('', $options->endpoint);
        self::assertSame('', $options->service);
        self::assertSame(0, $options->scheduleToCloseTimeout->s);
    }

    public function testWithEndpointSetsEndpoint(): void
    {
        $options = NexusOperationOptions::new()->withEndpoint('endpoint-1');

        self::assertSame('endpoint-1', $options->endpoint);
    }

    public function testWithEndpointRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus Endpoint must not be empty');

        NexusOperationOptions::new()->withEndpoint('');
    }

    public function testWithEndpointIsImmutable(): void
    {
        $original = NexusOperationOptions::new();
        $updated = $original->withEndpoint('endpoint-2');

        self::assertNotSame($original, $updated);
        self::assertSame('', $original->endpoint, 'Original must stay pristine');
        self::assertSame('endpoint-2', $updated->endpoint);
    }

    public function testWithServiceSetsService(): void
    {
        $options = NexusOperationOptions::new()->withService('MyService');

        self::assertSame('MyService', $options->service);
    }

    public function testWithServiceRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service Name must not be empty');

        NexusOperationOptions::new()->withService('');
    }

    public function testWithScheduleToCloseTimeoutAcceptsSeconds(): void
    {
        $options = NexusOperationOptions::new()->withScheduleToCloseTimeout(30);

        self::assertSame(30, $options->scheduleToCloseTimeout->s);
    }

    public function testWithScheduleToStartTimeoutAcceptsSeconds(): void
    {
        $options = NexusOperationOptions::new()->withScheduleToStartTimeout(5);

        self::assertSame(5, $options->scheduleToStartTimeout->s);
    }

    public function testWithScheduleToStartTimeoutIsImmutable(): void
    {
        $original = NexusOperationOptions::new();
        $updated = $original->withScheduleToStartTimeout(5);

        self::assertNotSame($original, $updated);
        self::assertSame(0, $original->scheduleToStartTimeout->s, 'Original must stay pristine');
        self::assertSame(5, $updated->scheduleToStartTimeout->s);
    }

    public function testWithStartToCloseTimeoutAcceptsSeconds(): void
    {
        $options = NexusOperationOptions::new()->withStartToCloseTimeout(10);

        self::assertSame(10, $options->startToCloseTimeout->s);
    }

    public function testWithStartToCloseTimeoutIsImmutable(): void
    {
        $original = NexusOperationOptions::new();
        $updated = $original->withStartToCloseTimeout(10);

        self::assertNotSame($original, $updated);
        self::assertSame(0, $original->startToCloseTimeout->s, 'Original must stay pristine');
        self::assertSame(10, $updated->startToCloseTimeout->s);
    }

    public function testMarshalsNewTimeoutsUnderWireKeys(): void
    {
        $options = NexusOperationOptions::new()
            ->withScheduleToStartTimeout(5)
            ->withStartToCloseTimeout(10);

        $marshalled = $this->marshal($options);

        self::assertArrayHasKey('scheduleToStartTimeout', $marshalled);
        self::assertArrayHasKey('startToCloseTimeout', $marshalled);
        self::assertSame(5_000_000_000, $marshalled['scheduleToStartTimeout']);
        self::assertSame(10_000_000_000, $marshalled['startToCloseTimeout']);
    }

    public function testCancellationTypeDefaultsToUnspecified(): void
    {
        $options = NexusOperationOptions::new();

        self::assertSame(NexusOperationCancellationType::Unspecified, $options->cancellationType);
    }

    public function testWithCancellationTypeAcceptsEnum(): void
    {
        $options = NexusOperationOptions::new()
            ->withCancellationType(NexusOperationCancellationType::TryCancel);

        self::assertSame(NexusOperationCancellationType::TryCancel, $options->cancellationType);
    }

    public function testWithCancellationTypeAcceptsInt(): void
    {
        $options = NexusOperationOptions::new()
            ->withCancellationType(NexusOperationCancellationType::WaitCompleted->value);

        self::assertSame(NexusOperationCancellationType::WaitCompleted, $options->cancellationType);
    }

    public function testWithCancellationTypeIsImmutable(): void
    {
        $original = NexusOperationOptions::new();
        $updated = $original->withCancellationType(NexusOperationCancellationType::Abandon);

        self::assertNotSame($original, $updated);
        self::assertSame(
            NexusOperationCancellationType::Unspecified,
            $original->cancellationType,
            'Original must stay pristine',
        );
        self::assertSame(NexusOperationCancellationType::Abandon, $updated->cancellationType);
    }
}
