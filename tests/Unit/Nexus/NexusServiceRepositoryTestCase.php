<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Handler\ServiceImplInstance;
use Temporal\Internal\Nexus\NexusServiceRepository;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class NexusServiceRepositoryTestCase extends AbstractUnit
{
    public function testEmptyRepository(): void
    {
        $repo = new NexusServiceRepository();

        self::assertSame([], $repo->getInstances());
    }

    public function testAddAndRetrieve(): void
    {
        $repo = new NexusServiceRepository();
        $instance = ServiceImplInstance::fromInstance(new TestGreetingServiceImpl());

        $repo->add($instance);

        $instances = $repo->getInstances();
        self::assertCount(1, $instances);
        self::assertSame($instance, $instances[0]);
    }

    public function testAddMultiple(): void
    {
        $repo = new NexusServiceRepository();
        $instance1 = ServiceImplInstance::fromInstance(new TestGreetingServiceImpl());

        $repo->add($instance1);
        // Adding same instance again (different services would have different names)
        $repo->add($instance1);

        self::assertCount(2, $repo->getInstances());
    }
}
