<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\Service;
use Temporal\Nexus\NexusOperationReader;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class NexusOperationReaderTestCase extends AbstractUnit
{
    public function testGetServiceNameReturnsExplicitName(): void
    {
        self::assertSame('MyNamedService', NexusOperationReader::getServiceName(NamedService::class));
    }

    public function testGetServiceNameFallsBackToShortNameWhenAttributeNameEmpty(): void
    {
        self::assertSame(
            'UnnamedServiceButAnnotated',
            NexusOperationReader::getServiceName(UnnamedServiceButAnnotated::class),
        );
    }

    public function testGetServiceNameThrowsWhenAttributeMissing(): void
    {
        // Silent fallback on missing #[Service] hid user errors — a class
        // without the attribute was the original reviewer finding.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing the #[');

        NexusOperationReader::getServiceName(NoServiceAttribute::class);
    }

    public function testGetOperationsReadsOwnMethods(): void
    {
        $ops = NexusOperationReader::getOperations(NamedService::class);

        self::assertArrayHasKey('sayHello', $ops);
        self::assertSame('sayHello', $ops['sayHello']['name']);
        self::assertSame('string', $ops['sayHello']['returnType']);
    }

    public function testGetOperationsUsesExplicitOperationName(): void
    {
        $ops = NexusOperationReader::getOperations(NamedOperationService::class);

        self::assertSame('greet.user', $ops['sayHello']['name']);
    }

    public function testGetOperationsWalksParentInterfaces(): void
    {
        // Inherited #[Operation] methods on a parent interface must be
        // visible on the child — regression test for a reviewer finding
        // that getOperations() only looked at direct-declared methods.
        $ops = NexusOperationReader::getOperations(ChildService::class);

        self::assertArrayHasKey('inherited', $ops, 'inherited operation must be detected');
        self::assertArrayHasKey('ownOp', $ops, 'own operation must be detected');
    }

    public function testGetOperationsChildOverridesParentName(): void
    {
        $ops = NexusOperationReader::getOperations(OverridingChildService::class);

        // The child's attribute takes precedence when both parent and child
        // declare a method of the same name with #[Operation].
        self::assertSame('child-name', $ops['dupe']['name']);
    }

    public function testGetOperationsReturnsMixedForMissingReturnType(): void
    {
        $ops = NexusOperationReader::getOperations(UntypedReturnService::class);

        self::assertSame('mixed', $ops['anything']['returnType']);
    }

    public function testGetOperationsSkipsMethodsWithoutAttribute(): void
    {
        $ops = NexusOperationReader::getOperations(NamedService::class);

        self::assertArrayNotHasKey('helper', $ops);
    }
}

#[Service(name: 'MyNamedService')]
interface NamedService
{
    #[Operation]
    public function sayHello(string $name): string;

    // No #[Operation] — should be skipped.
    public function helper(): void;
}

#[Service]
interface UnnamedServiceButAnnotated
{
    #[Operation]
    public function doIt(): string;
}

interface NoServiceAttribute
{
    #[Operation]
    public function op(): string;
}

#[Service(name: 'WithExplicitOpName')]
interface NamedOperationService
{
    #[Operation(name: 'greet.user')]
    public function sayHello(string $name): string;
}

#[Service(name: 'Parent')]
interface ParentService
{
    #[Operation]
    public function inherited(): string;
}

#[Service(name: 'Child')]
interface ChildService extends ParentService
{
    #[Operation]
    public function ownOp(): string;
}

#[Service(name: 'ParentDupe')]
interface ParentWithDupe
{
    #[Operation(name: 'parent-name')]
    public function dupe(): string;
}

#[Service(name: 'OverridingChild')]
interface OverridingChildService extends ParentWithDupe
{
    #[Operation(name: 'child-name')]
    public function dupe(): string;
}

#[Service(name: 'Untyped')]
interface UntypedReturnService
{
    #[Operation]
    public function anything(); // no return type declared
}
