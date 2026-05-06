<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\NexusOperationCancellationType;

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusOperationCancellationType::class)]
final class NexusOperationCancellationTypeTestCase extends AbstractUnit
{
    public function testEnumValuesMatchSdkGoIotaOrder(): void
    {
        // Wire-level integers must stay aligned with sdk-go's iota order;
        // drift here is a wire-level break.
        self::assertSame(0, NexusOperationCancellationType::Unspecified->value);
        self::assertSame(1, NexusOperationCancellationType::Abandon->value);
        self::assertSame(2, NexusOperationCancellationType::TryCancel->value);
        self::assertSame(3, NexusOperationCancellationType::WaitRequested->value);
        self::assertSame(4, NexusOperationCancellationType::WaitCompleted->value);
    }

    public function testFromIntProducesExpectedCase(): void
    {
        self::assertSame(
            NexusOperationCancellationType::Unspecified,
            NexusOperationCancellationType::from(0),
        );
        self::assertSame(
            NexusOperationCancellationType::Abandon,
            NexusOperationCancellationType::from(1),
        );
        self::assertSame(
            NexusOperationCancellationType::TryCancel,
            NexusOperationCancellationType::from(2),
        );
        self::assertSame(
            NexusOperationCancellationType::WaitRequested,
            NexusOperationCancellationType::from(3),
        );
        self::assertSame(
            NexusOperationCancellationType::WaitCompleted,
            NexusOperationCancellationType::from(4),
        );
    }
}
