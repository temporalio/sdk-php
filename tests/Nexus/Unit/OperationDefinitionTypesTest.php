<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\OperationDefinition;
use Temporal\Nexus\ServiceDefinition;
use Temporal\Tests\Nexus\Fixture\Service\IntersectionInputServiceInterface;
use Temporal\Tests\Nexus\Fixture\Service\NullableInputServiceInterface;
use Temporal\Tests\Nexus\Fixture\Service\UnionInputServiceInterface;
use Temporal\Tests\Nexus\Fixture\Service\UnionOutputServiceInterface;
use Temporal\Tests\Nexus\Fixture\Service\UntypedInputServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationDefinition::class)]
final class OperationDefinitionTypesTest extends TestCase
{
    public function testAcceptsNullableInputAndOutputType(): void
    {
        $defn = ServiceDefinition::fromClass(NullableInputServiceInterface::class);

        self::assertArrayHasKey('operation', $defn->operations);
        $op = $defn->operations['operation'];
        self::assertSame('?string', $op->inputType);
        self::assertSame('?string', $op->outputType);
    }

    public function testRejectsUnionInputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Union types.*parameter \$input of/');
        ServiceDefinition::fromClass(UnionInputServiceInterface::class);
    }

    public function testRejectsUnionOutputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Union types.*return type of/');
        ServiceDefinition::fromClass(UnionOutputServiceInterface::class);
    }

    public function testRejectsIntersectionInputType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Intersection types.*parameter \$input of/');
        ServiceDefinition::fromClass(IntersectionInputServiceInterface::class);
    }

    public function testUntypedParameterFallsBackToMixed(): void
    {
        $defn = ServiceDefinition::fromClass(UntypedInputServiceInterface::class);

        $op = $defn->operations['operation'];
        self::assertSame('mixed', $op->inputType);
        self::assertSame('string', $op->outputType);
    }

    public function testOperationDefinitionFromMethodMissingAttribute(): void
    {
        $iface = new class {
            public function plainMethod(): void {}
        };
        $method = new \ReflectionMethod($iface::class, 'plainMethod');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing #[Operation] attribute');
        OperationDefinition::fromMethod($method);
    }

    public function testOperationDefinitionFromMethodTooManyParameters(): void
    {
        $fixture = new class {
            #[\Temporal\Nexus\Attribute\Operation]
            public function bad(string $a, string $b): void {}
        };
        $method = new \ReflectionMethod($fixture::class, 'bad');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Can have no more than one parameter');
        OperationDefinition::fromMethod($method);
    }

    public function testOperationDefinitionRejectsStatic(): void
    {
        $fixture = new class {
            #[\Temporal\Nexus\Attribute\Operation]
            public static function staticOp(): void {}
        };
        $method = new \ReflectionMethod($fixture::class, 'staticOp');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot be static');
        OperationDefinition::fromMethod($method);
    }
}
