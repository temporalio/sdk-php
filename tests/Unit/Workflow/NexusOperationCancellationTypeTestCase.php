<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\NexusOperationCancellationType;

/**
 * @group unit
 * @group nexus
 */
final class NexusOperationCancellationTypeTestCase extends AbstractUnit
{
    public function testEnumCasesMatchConstants(): void
    {
        // The enum cases are declared as `case Name = self::CONSTANT` so the
        // integer wire values stay in sync with sdk-go's iota order. Drift
        // here is a wire-level break — pin both sides explicitly.
        self::assertSame(
            NexusOperationCancellationType::UNSPECIFIED,
            NexusOperationCancellationType::Unspecified->value,
        );
        self::assertSame(
            NexusOperationCancellationType::ABANDON,
            NexusOperationCancellationType::Abandon->value,
        );
        self::assertSame(
            NexusOperationCancellationType::TRY_CANCEL,
            NexusOperationCancellationType::TryCancel->value,
        );
        self::assertSame(
            NexusOperationCancellationType::WAIT_REQUESTED,
            NexusOperationCancellationType::WaitRequested->value,
        );
        self::assertSame(
            NexusOperationCancellationType::WAIT_COMPLETED,
            NexusOperationCancellationType::WaitCompleted->value,
        );
    }

    public function testConstantValuesMatchSdkGoIotaOrder(): void
    {
        self::assertSame(0, NexusOperationCancellationType::UNSPECIFIED);
        self::assertSame(1, NexusOperationCancellationType::ABANDON);
        self::assertSame(2, NexusOperationCancellationType::TRY_CANCEL);
        self::assertSame(3, NexusOperationCancellationType::WAIT_REQUESTED);
        self::assertSame(4, NexusOperationCancellationType::WAIT_COMPLETED);
    }

    public function testFromIntProducesExpectedCase(): void
    {
        self::assertSame(
            NexusOperationCancellationType::Unspecified,
            NexusOperationCancellationType::from(NexusOperationCancellationType::UNSPECIFIED),
        );
        self::assertSame(
            NexusOperationCancellationType::Abandon,
            NexusOperationCancellationType::from(NexusOperationCancellationType::ABANDON),
        );
        self::assertSame(
            NexusOperationCancellationType::TryCancel,
            NexusOperationCancellationType::from(NexusOperationCancellationType::TRY_CANCEL),
        );
        self::assertSame(
            NexusOperationCancellationType::WaitRequested,
            NexusOperationCancellationType::from(NexusOperationCancellationType::WAIT_REQUESTED),
        );
        self::assertSame(
            NexusOperationCancellationType::WaitCompleted,
            NexusOperationCancellationType::from(NexusOperationCancellationType::WAIT_COMPLETED),
        );
    }
}
