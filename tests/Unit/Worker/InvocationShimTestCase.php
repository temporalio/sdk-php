<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationFailure;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationResult;
use Temporal\Worker\InvocationFailure;
use Temporal\Worker\InvocationResult;

final class InvocationShimTestCase extends TestCase
{
    public function testActivityInvocationResultFactoryReturnsTheShimType(): void
    {
        $result = ActivityInvocationResult::fromValue('x', DataConverter::createDefault());

        self::assertInstanceOf(ActivityInvocationResult::class, $result);
        self::assertInstanceOf(InvocationResult::class, $result);
    }

    public function testActivityInvocationFailureFactoryReturnsTheShimType(): void
    {
        $failure = ActivityInvocationFailure::fromThrowable(new \RuntimeException('boom'));

        self::assertInstanceOf(ActivityInvocationFailure::class, $failure);
        self::assertInstanceOf(InvocationFailure::class, $failure);
        self::assertInstanceOf(\RuntimeException::class, $failure->toThrowable());
    }
}
