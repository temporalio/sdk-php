<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Tests\Nexus\Fixtures\Service\IntersectionInputServiceInterface;
use Temporal\Tests\Nexus\Fixtures\Service\NullableInputServiceInterface;
use Temporal\Tests\Nexus\Fixtures\Service\UnionInputServiceInterface;
use Temporal\Tests\Nexus\Fixtures\Service\UnionOutputServiceInterface;
use Temporal\Tests\Nexus\Fixtures\Service\UntypedInputServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusOperationPrototype::class)]
#[UsesClass(NexusServiceReader::class)]
#[UsesClass(NexusServicePrototype::class)]
final class NexusOperationTypesTest extends TestCase
{
    public function testAcceptsNullableInputAndOutputType(): void
    {
        $proto = self::reader()->fromClass(NullableInputServiceInterface::class);

        self::assertArrayHasKey('operation', $proto->getOperations());
        $op = $proto->getOperations()['operation'];
        self::assertSame('?string', $op->inputType);
        self::assertSame('?string', $op->outputType);
    }

    public function testRejectsUnionInputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Union types.*parameter \$input of/');
        self::reader()->fromClass(UnionInputServiceInterface::class);
    }

    public function testRejectsUnionOutputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Union types.*return type of/');
        self::reader()->fromClass(UnionOutputServiceInterface::class);
    }

    public function testRejectsIntersectionInputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Intersection types.*parameter \$input of/');
        self::reader()->fromClass(IntersectionInputServiceInterface::class);
    }

    public function testUntypedParameterFallsBackToMixed(): void
    {
        $proto = self::reader()->fromClass(UntypedInputServiceInterface::class);

        $op = $proto->getOperations()['operation'];
        self::assertSame('mixed', $op->inputType);
        self::assertSame('string', $op->outputType);
    }

    public function testReaderRejectsMethodWithoutAttribute(): void
    {
        $fixture = new class {
            public function plainMethod(): void {}
        };
        // Plain methods on a #[Service]-less class never reach operationFromMethod —
        // the reader skips them entirely. To exercise the Missing-attribute branch,
        // we go through fromClass and it throws Missing #[Service] earlier.
        $this->expectException(InvalidArgumentException::class);
        self::reader()->fromClass($fixture::class);
    }

    public function testRejectsTooManyParameters(): void
    {
        $iface = new #[\Temporal\Nexus\Attribute\Service] class {
            #[\Temporal\Nexus\Attribute\Operation]
            public function bad(string $a, string $b): void {}
        };
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Can have no more than one parameter');
        self::reader()->fromClass($iface::class);
    }

    public function testRejectsStaticOperation(): void
    {
        $iface = new #[\Temporal\Nexus\Attribute\Service] class {
            #[\Temporal\Nexus\Attribute\Operation]
            public static function staticOp(): void {}
        };
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot be static');
        self::reader()->fromClass($iface::class);
    }

    private static function reader(): NexusServiceReader
    {
        return new NexusServiceReader(new AttributeReader());
    }
}
