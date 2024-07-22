<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\CancelTryCancel;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;

class CancelTryCancelTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Workflow')] WorkflowStubInterface $stub): void
    {
        self::assertSame('cancelled', $stub->getResult(timeout: 10));
    }
}
