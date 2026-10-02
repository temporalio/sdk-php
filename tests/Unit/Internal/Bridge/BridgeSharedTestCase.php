<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Bridge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Bridge\Bridge;

#[CoversClass(Bridge::class)]
final class BridgeSharedTestCase extends TestCase
{
    private const PARENT_PID = 1;

    public function testRuntimeStartedBeforeForkIsRejectedInTheChild(): void
    {
        $class = new \ReflectionClass(Bridge::class);
        $class->setStaticPropertyValue('shared', $class->newInstanceWithoutConstructor());
        $class->setStaticPropertyValue('sharedPid', self::PARENT_PID);

        try {
            $this->expectExceptionObject(new \LogicException(Bridge::FORKED_AFTER_START));
            Bridge::shared();
        } finally {
            $class->setStaticPropertyValue('shared', null);
            $class->setStaticPropertyValue('sharedPid', 0);
        }
    }
}
