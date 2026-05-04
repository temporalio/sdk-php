<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Prototype\NexusServiceCollection;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
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

class DupAlphaImpl implements DupAlphaInterface
{
    public function op(string $in): string
    {
        return "alpha:{$in}";
    }
}

class DupBetaImpl implements DupBetaInterface
{
    public function op(string $in): string
    {
        return "beta:{$in}";
    }
}

/**
 * @group unit
 * @group nexus
 */
#[CoversClass(NexusServiceCollection::class)]
#[UsesClass(NexusServicePrototype::class)]
#[UsesClass(NexusServiceReader::class)]
final class NexusServiceCollectionTestCase extends AbstractUnit
{
    public function testEmptyRepository(): void
    {
        $repo = new NexusServiceCollection();

        self::assertCount(0, $repo);
    }

    public function testAddAndRetrieve(): void
    {
        $repo = new NexusServiceCollection();
        $prototype = self::buildPrototype(new TestGreetingServiceImpl());

        $repo->add($prototype);

        self::assertCount(1, $repo);
        $items = \iterator_to_array($repo);
        self::assertSame($prototype, \reset($items));
    }

    public function testRejectsDuplicateServiceName(): void
    {
        $repo = new NexusServiceCollection();
        $repo->add(self::buildPrototype(new DupAlphaImpl()));

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/Entry with same identifier "Dup"/');
        $repo->add(self::buildPrototype(new DupBetaImpl()));
    }

    public function testGetIteratorReturnsItemsInInsertionOrder(): void
    {
        $repo = new NexusServiceCollection();

        $alpha = self::buildPrototype(new DupAlphaImpl());
        $greeting = self::buildPrototype(new TestGreetingServiceImpl());

        $repo->add($alpha);
        $repo->add($greeting);

        self::assertCount(2, $repo);
        $items = \array_values(\iterator_to_array($repo));
        self::assertSame($alpha, $items[0]);
        self::assertSame($greeting, $items[1]);
    }

    public function testSelfRegistrationRejected(): void
    {
        // Registering reflection-built prototypes from the same class twice fails:
        // both produce the same service name, and the collection guards by ID.
        $repo = new NexusServiceCollection();
        $instance = new DupAlphaImpl();
        $repo->add(self::buildPrototype($instance));

        $this->expectException(\OutOfBoundsException::class);
        $repo->add(self::buildPrototype($instance));
    }

    /**
     * Reproduces the Worker registration flow: read prototype, bind the
     * service instance via factory closure.
     */
    private static function buildPrototype(object $instance): NexusServicePrototype
    {
        $reader = new NexusServiceReader(new AttributeReader());
        return $reader->fromClass($instance::class)->withInstance($instance);
    }
}
