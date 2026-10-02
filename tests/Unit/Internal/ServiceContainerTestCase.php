<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\DataConverter\DataConverter;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\ServiceContainer;
use Temporal\Tests\Unit\Activity\DummyActivity;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;

#[CoversClass(ServiceContainer::class)]
final class ServiceContainerTestCase extends TestCase
{
    public function testActivityStubPrototypesAreReadOncePerClass(): void
    {
        $services = ServiceContainer::fromWorkerFactory(
            new WorkerFactoryMock(DataConverter::createDefault()),
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new NullLogger(),
        );

        $first = $services->activityStubPrototypes(DummyActivity::class);

        self::assertSame('DummyActivityDoNothing', $first[0]->getID());
        self::assertSame($first, $services->activityStubPrototypes(DummyActivity::class));
        self::assertSame($first[0], $services->activityStubPrototypes(DummyActivity::class)[0]);
    }
}
