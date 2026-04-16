<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\ServiceImplInstance;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
use Temporal\Internal\Nexus\NexusServiceRepository;
use Temporal\Tests\Unit\AbstractUnit;

// Two interfaces declaring the same service name "Dup" — used to exercise the dedup path.
#[Service(name: 'Dup')]
interface DupAlphaInterface
{
    #[Operation]
    public function op(string $in): string;
}

#[Service(name: 'Dup')]
interface DupBetaInterface
{
    #[Operation]
    public function op(string $in): string;
}

#[ServiceImpl(service: DupAlphaInterface::class)]
class DupAlphaImpl
{
    #[OperationImpl]
    public function op(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $c, OperationStartDetails $d, ?string $in) => "alpha:{$in}",
        );
    }
}

#[ServiceImpl(service: DupBetaInterface::class)]
class DupBetaImpl
{
    #[OperationImpl]
    public function op(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $c, OperationStartDetails $d, ?string $in) => "beta:{$in}",
        );
    }
}

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

    public function testRejectsDuplicateServiceName(): void
    {
        $repo = new NexusServiceRepository();
        $repo->add(ServiceImplInstance::fromInstance(new DupAlphaImpl()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Nexus service "Dup" is already registered/');
        $repo->add(ServiceImplInstance::fromInstance(new DupBetaImpl()));
    }

    public function testErrorMentionsBothRegistrations(): void
    {
        $repo = new NexusServiceRepository();
        $repo->add(ServiceImplInstance::fromInstance(new DupAlphaImpl()));

        try {
            $repo->add(ServiceImplInstance::fromInstance(new DupBetaImpl()));
            self::fail('expected throw');
        } catch (\InvalidArgumentException $e) {
            self::assertMatchesRegularExpression(
                '/ops: op/',
                $e->getMessage(),
                'Error should include operation names for debugging',
            );
        }
    }

    public function testGetInstancesReturnsListOrdered(): void
    {
        // Two different service names — both accepted; insertion order preserved.
        $repo = new NexusServiceRepository();

        $alpha = ServiceImplInstance::fromInstance(new DupAlphaImpl());
        $greeting = ServiceImplInstance::fromInstance(new TestGreetingServiceImpl());

        $repo->add($alpha);
        $repo->add($greeting);

        $instances = $repo->getInstances();
        self::assertCount(2, $instances);
        self::assertSame($alpha, $instances[0]);
        self::assertSame($greeting, $instances[1]);
    }

    public function testSelfRegistrationRejected(): void
    {
        // Registering reflection-built instances from the same object twice also fails,
        // because both produce the same service name.
        $repo = new NexusServiceRepository();
        $impl = new DupAlphaImpl();
        $repo->add(ServiceImplInstance::fromInstance($impl));

        $this->expectException(\InvalidArgumentException::class);
        $repo->add(ServiceImplInstance::fromInstance($impl));
    }
}
