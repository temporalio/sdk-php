<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\Basic;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;

class BasicTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Workflow')] WorkflowStubInterface $stub): void
    {
        self::assertSame('echo', $stub->getResult());
    }
}
