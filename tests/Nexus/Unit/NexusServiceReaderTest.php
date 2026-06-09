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
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Tests\Nexus\Fixtures\Service\UnionInputServiceInterface;
use Temporal\Tests\Nexus\Fixtures\Service\UntypedInputServiceInterface;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\AmbiguousServiceImpl;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\DiamondFinalInterface;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\EmptyService;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\InvalidAsyncReturnTypeService;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\InvalidServiceDuplicateOperation;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\InvalidServiceNoAnnotation;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\ServiceAsClass;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\InvalidServiceWithOperations;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\InvalidSubService;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\OperationOverrideMismatchService;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\ServiceWithPlainParentInterface;
use Temporal\Tests\Nexus\Fixtures\ServiceDefinition\ValidServiceWithOperations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NexusServiceReader::class)]
#[UsesClass(NexusServicePrototype::class)]
final class NexusServiceReaderTest extends TestCase
{
    public function testInvalidServiceNoAnnotation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing #[Service] attribute');
        self::reader()->fromClass(InvalidServiceNoAnnotation::class);
    }

    public function testServiceAttributeOnClassIsAccepted(): void
    {
        $proto = self::reader()->fromClass(ServiceAsClass::class);

        self::assertSame('ServiceAsClass', $proto->getID());
        self::assertCount(1, $proto->getOperations());
        self::assertArrayHasKey('classOperation', $proto->getOperations());
        self::assertSame('string', $proto->getOperations()['classOperation']->inputType->getName());
        self::assertSame('string', $proto->getOperations()['classOperation']->outputType->getName());
    }

    public function testMultipleServiceInterfacesAreAmbiguous(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('implements multiple #[Service] interfaces');
        self::reader()->fromClass(AmbiguousServiceImpl::class);
    }

    public function testInvalidSubServiceNameMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match the expected name on the contract');
        self::reader()->fromClass(InvalidSubService::class);
    }

    public function testInvalidServiceWithOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('operation(s) were invalid');
        self::reader()->fromClass(InvalidServiceWithOperations::class);
    }

    public function testInvalidServiceDuplicateOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Multiple operations named 'duplicateWhenNameOverridden1'");
        self::reader()->fromClass(InvalidServiceDuplicateOperation::class);
    }

    public function testAsyncOperationWithInvalidReturnTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must declare a `Temporal\Nexus\WorkflowHandle` or `Temporal\Nexus\OperationInfo` return type');
        self::reader()->fromClass(InvalidAsyncReturnTypeService::class);
    }

    public function testValidService(): void
    {
        $proto = self::reader()->fromClass(ValidServiceWithOperations::class);

        self::assertSame('ValidServiceWithOperations', $proto->getID());

        self::assertOperation($proto, 'superMethod',           input: 'void',   output: 'void');
        self::assertOperation($proto, 'superInterfaceOnly',    input: 'void',   output: 'void');
        self::assertOperation($proto, 'noParamNoReturn',       input: 'void',   output: 'void');
        self::assertOperation($proto, 'noParamSingleReturn',   input: 'void',   output: 'string');
        self::assertOperation($proto, 'singleParamNoReturn',   input: 'string', output: 'void');
        self::assertOperation($proto, 'singleParamSingleReturn', input: 'string', output: 'string');
        self::assertOperation($proto, 'custom-name',           input: 'void',   output: 'void');
    }

    public function testEmptyServiceRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No operations defined');
        self::reader()->fromClass(EmptyService::class);
    }

    public function testParentInterfaceWithoutServiceAttributeIsSkipped(): void
    {
        $proto = self::reader()->fromClass(ServiceWithPlainParentInterface::class);
        self::assertSame('ServiceWithPlainParentInterface', $proto->getID());
        self::assertArrayHasKey('ownOperation', $proto->getOperations());
        self::assertArrayHasKey('inheritedOperation', $proto->getOperations());
    }

    public function testOperationOverrideWithMismatchingNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mismatches against another operation');
        self::reader()->fromClass(OperationOverrideMismatchService::class);
    }

    public function testDiamondInheritanceIsHandled(): void
    {
        $proto = self::reader()->fromClass(DiamondFinalInterface::class);
        self::assertSame('Diamond', $proto->getID());
        self::assertCount(1, $proto->getOperations());
        self::assertArrayHasKey('commonOp', $proto->getOperations());
    }

    public function testUntypedParameterFallsBackToMixed(): void
    {
        $proto = self::reader()->fromClass(UntypedInputServiceInterface::class);
        self::assertSame('mixed', $proto->getOperations()['operation']->inputType->getName());
    }

    public function testUnionParameterFallsBackToMixed(): void
    {
        $proto = self::reader()->fromClass(UnionInputServiceInterface::class);
        self::assertSame('mixed', $proto->getOperations()['operation']->inputType->getName());
    }

    private static function reader(): NexusServiceReader
    {
        return new NexusServiceReader(new AttributeReader());
    }

    private static function assertOperation(
        NexusServicePrototype $proto,
        string $name,
        string $input,
        string $output,
    ): void {
        $operations = $proto->getOperations();
        self::assertArrayHasKey($name, $operations);
        $op = $operations[$name];
        self::assertSame($name, $op->name);
        self::assertSame($input, self::typeName($op->inputType), "inputType for {$name}");
        self::assertSame($output, self::typeName($op->outputType), "outputType for {$name}");
    }

    private static function typeName(\Temporal\DataConverter\Type $type): string
    {
        $name = $type->getName();
        return $type->allowsNull() && !\in_array($name, ['mixed', 'void', 'null'], true)
            ? '?' . $name
            : $name;
    }
}
