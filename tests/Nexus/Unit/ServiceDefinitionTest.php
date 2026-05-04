<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\ServiceDefinition;
use Temporal\Tests\Nexus\Fixture\Service\UnionInputServiceInterface;
use Temporal\Tests\Nexus\Fixture\Service\UntypedInputServiceInterface;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\DiamondFinalInterface;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\EmptyService;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\InvalidServiceDuplicateOperation;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\InvalidServiceNoAnnotation;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\ServiceAsClass;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\InvalidServiceWithOperations;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\InvalidSubService;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\OperationOverrideMismatchService;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\ServiceWithPlainParentInterface;
use Temporal\Tests\Nexus\Fixture\ServiceDefinition\ValidServiceWithOperations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceDefinition::class)]
final class ServiceDefinitionTest extends TestCase
{
    public function testInvalidServiceNoAnnotation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing #[Service] attribute');
        ServiceDefinition::fromClass(InvalidServiceNoAnnotation::class);
    }

    public function testServiceAttributeOnClassIsAccepted(): void
    {
        $defn = ServiceDefinition::fromClass(ServiceAsClass::class);

        self::assertSame('ServiceAsClass', $defn->name);
        self::assertCount(1, $defn->operations);
        self::assertArrayHasKey('classOperation', $defn->operations);
        self::assertSame('string', $defn->operations['classOperation']->inputType);
        self::assertSame('string', $defn->operations['classOperation']->outputType);
    }

    public function testInvalidSubServiceNameMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match the expected name on the contract');
        ServiceDefinition::fromClass(InvalidSubService::class);
    }

    public function testInvalidServiceWithOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('operation(s) were invalid');
        ServiceDefinition::fromClass(InvalidServiceWithOperations::class);
    }

    public function testInvalidServiceDuplicateOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Multiple operations named 'duplicateWhenNameOverridden1'");
        ServiceDefinition::fromClass(InvalidServiceDuplicateOperation::class);
    }

    public function testValidService(): void
    {
        $defn = ServiceDefinition::fromClass(ValidServiceWithOperations::class);

        self::assertSame('ValidServiceWithOperations', $defn->name);

        self::assertOperation($defn, 'superMethod',           input: 'void',   output: 'void');
        self::assertOperation($defn, 'superInterfaceOnly',    input: 'void',   output: 'void');
        self::assertOperation($defn, 'noParamNoReturn',       input: 'void',   output: 'void');
        self::assertOperation($defn, 'noParamSingleReturn',   input: 'void',   output: 'string');
        self::assertOperation($defn, 'singleParamNoReturn',   input: 'string', output: 'void');
        self::assertOperation($defn, 'singleParamSingleReturn', input: 'string', output: 'string');
        self::assertOperation($defn, 'custom-name',           input: 'void',   output: 'void');
    }

    public function testEmptyServiceRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No operations defined');
        ServiceDefinition::fromClass(EmptyService::class);
    }

    public function testParentInterfaceWithoutServiceAttributeIsSkipped(): void
    {
        $defn = ServiceDefinition::fromClass(ServiceWithPlainParentInterface::class);
        self::assertSame('ServiceWithPlainParentInterface', $defn->name);
        self::assertArrayHasKey('ownOperation', $defn->operations);
        self::assertArrayHasKey('inheritedOperation', $defn->operations);
    }

    public function testOperationOverrideWithMismatchingNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mismatches against another operation');
        ServiceDefinition::fromClass(OperationOverrideMismatchService::class);
    }

    public function testDiamondInheritanceIsHandled(): void
    {
        $defn = ServiceDefinition::fromClass(DiamondFinalInterface::class);
        self::assertSame('Diamond', $defn->name);
        self::assertCount(1, $defn->operations);
        self::assertArrayHasKey('commonOp', $defn->operations);
    }

    public function testUntypedParameterFallsBackToMixed(): void
    {
        $defn = ServiceDefinition::fromClass(UntypedInputServiceInterface::class);
        self::assertSame('mixed', $defn->operations['operation']->inputType);
    }

    public function testUnionParameterIsRejectedAtFromMethod(): void
    {
        // The signature key still includes the union (covering reflectionTypeKey's
        // composite branch) before fromMethod rejects it.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Union types');
        ServiceDefinition::fromClass(UnionInputServiceInterface::class);
    }

    private static function assertOperation(
        ServiceDefinition $defn,
        string $name,
        string $input,
        string $output,
    ): void {
        self::assertArrayHasKey($name, $defn->operations);
        $op = $defn->operations[$name];
        self::assertSame($name, $op->name);
        self::assertSame($input, $op->inputType, "inputType for {$name}");
        self::assertSame($output, $op->outputType, "outputType for {$name}");
    }
}
