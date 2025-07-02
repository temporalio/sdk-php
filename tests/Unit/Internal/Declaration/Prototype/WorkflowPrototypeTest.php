<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Declaration\Prototype;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\Prototype\QueryDefinition;
use Temporal\Internal\Declaration\Prototype\SignalDefinition;
use Temporal\Internal\Declaration\Prototype\UpdateDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Workflow\HandlerUnfinishedPolicy;
use Temporal\Workflow\ReturnType;

#[CoversClass(WorkflowPrototype::class)]
final class WorkflowPrototypeTest extends TestCase
{
    private \ReflectionClass $reflectionClass;
    private \ReflectionMethod $reflectionMethod;

    public static function provideInvalidWorkflowNames(): \Generator
    {
        yield 'workflow with builtin prefix' => ['__temporal_TestWorkflow'];
        yield 'workflow with exact builtin prefix' => ['__temporal_'];
        yield 'workflow with builtin prefix and suffix' => ['__temporal_workflow_test'];
    }

    public static function provideInvalidQueryNames(): \Generator
    {
        yield 'query with builtin prefix' => ['__temporal_testQuery'];
        yield 'query with exact builtin prefix' => ['__temporal_'];
        yield 'reserved stack trace query' => ['__stack_trace'];
        yield 'reserved enhanced stack trace query' => ['__enhanced_stack_trace'];
    }

    public static function provideInvalidSignalNames(): \Generator
    {
        yield 'signal with builtin prefix' => ['__temporal_testSignal'];
        yield 'signal with exact builtin prefix' => ['__temporal_'];
        yield 'signal with builtin prefix and suffix' => ['__temporal_signal_test'];
    }

    public static function provideInvalidUpdateNames(): \Generator
    {
        yield 'update with builtin prefix' => ['__temporal_testUpdate'];
        yield 'update with exact builtin prefix' => ['__temporal_'];
        yield 'update with builtin prefix and suffix' => ['__temporal_update_test'];
    }

    public function testConstructorCreatesWorkflowPrototypeWithValidName(): void
    {
        // Arrange
        $name = 'TestWorkflow';
        $handler = $this->reflectionMethod;
        $class = $this->reflectionClass;

        // Act
        $prototype = new WorkflowPrototype($name, $handler, $class);

        // Assert
        self::assertSame($name, $prototype->getID());
        self::assertSame($handler, $prototype->getHandler());
        self::assertSame($class, $prototype->getClass());
        self::assertFalse($prototype->hasInitializer());
        self::assertNull($prototype->getCronSchedule());
        self::assertNull($prototype->getMethodRetry());
        self::assertNull($prototype->getReturnType());
        self::assertSame([], $prototype->getQueryHandlers());
        self::assertSame([], $prototype->getSignalHandlers());
        self::assertSame([], $prototype->getUpdateHandlers());
        self::assertSame([], $prototype->getValidateUpdateHandlers());
    }

    public function testConstructorWithNullHandler(): void
    {
        // Arrange
        $name = 'TestWorkflow';
        $class = $this->reflectionClass;

        // Act
        $prototype = new WorkflowPrototype($name, null, $class);

        // Assert
        self::assertSame($name, $prototype->getID());
        self::assertNull($prototype->getHandler());
        self::assertSame($class, $prototype->getClass());
    }

    #[DataProvider('provideInvalidWorkflowNames')]
    public function testConstructorThrowsExceptionForInvalidWorkflowNames(string $invalidName): void
    {
        // Assert (before Act for exceptions)
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new WorkflowPrototype($invalidName, null, $this->reflectionClass);
    }

    public function testSetAndGetHasInitializer(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);

        // Act & Assert - Initially false
        self::assertFalse($prototype->hasInitializer());

        // Act - Set to true
        $prototype->setHasInitializer(true);

        // Assert
        self::assertTrue($prototype->hasInitializer());

        // Act - Set back to false
        $prototype->setHasInitializer(false);

        // Assert
        self::assertFalse($prototype->hasInitializer());
    }

    public function testSetAndGetCronSchedule(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $cronSchedule = new CronSchedule('0 0 * * *'); // Every day at midnight

        // Act & Assert - Initially null
        self::assertNull($prototype->getCronSchedule());

        // Act - Set cron schedule
        $prototype->setCronSchedule($cronSchedule);

        // Assert
        self::assertSame($cronSchedule, $prototype->getCronSchedule());

        // Act - Set to null
        $prototype->setCronSchedule(null);

        // Assert
        self::assertNull($prototype->getCronSchedule());
    }

    public function testSetAndGetMethodRetry(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $methodRetry = MethodRetry::new();

        // Act & Assert - Initially null
        self::assertNull($prototype->getMethodRetry());

        // Act - Set method retry
        $prototype->setMethodRetry($methodRetry);

        // Assert
        self::assertSame($methodRetry, $prototype->getMethodRetry());

        // Act - Set to null
        $prototype->setMethodRetry(null);

        // Assert
        self::assertNull($prototype->getMethodRetry());
    }

    public function testSetAndGetReturnType(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $returnType = new ReturnType('string');

        // Act & Assert - Initially null
        self::assertNull($prototype->getReturnType());

        // Act - Set return type
        $prototype->setReturnType($returnType);

        // Assert
        self::assertSame($returnType, $prototype->getReturnType());

        // Act - Set to null
        $prototype->setReturnType(null);

        // Assert
        self::assertNull($prototype->getReturnType());
    }

    public function testAddAndGetQueryHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $queryDefinition = new QueryDefinition(
            'testQuery',
            'string',
            $this->reflectionMethod,
            'Test query description',
        );

        // Act & Assert - Initially empty
        self::assertSame([], $prototype->getQueryHandlers());

        // Act - Add query handler
        $prototype->addQueryHandler($queryDefinition);

        // Assert
        $handlers = $prototype->getQueryHandlers();
        self::assertCount(1, $handlers);
        self::assertArrayHasKey('testQuery', $handlers);
        self::assertSame($queryDefinition, $handlers['testQuery']);
    }

    public function testAddMultipleQueryHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstQuery = new QueryDefinition(
            'firstQuery',
            'string',
            $this->reflectionMethod,
            'First query',
        );
        $secondQuery = new QueryDefinition(
            'secondQuery',
            'int',
            $this->reflectionMethod,
            'Second query',
        );

        // Act
        $prototype->addQueryHandler($firstQuery);
        $prototype->addQueryHandler($secondQuery);

        // Assert
        $handlers = $prototype->getQueryHandlers();
        self::assertCount(2, $handlers);
        self::assertArrayHasKey('firstQuery', $handlers);
        self::assertArrayHasKey('secondQuery', $handlers);
        self::assertSame($firstQuery, $handlers['firstQuery']);
        self::assertSame($secondQuery, $handlers['secondQuery']);
    }

    #[DataProvider('provideInvalidQueryNames')]
    public function testAddQueryHandlerThrowsExceptionForInvalidNames(string $invalidName): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $queryDefinition = new QueryDefinition(
            $invalidName,
            'string',
            $this->reflectionMethod,
            'Test query description',
        );

        // Assert (before Act for exceptions)
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $prototype->addQueryHandler($queryDefinition);
    }

    public function testAddAndGetSignalHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $signalDefinition = new SignalDefinition(
            'testSignal',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            $this->reflectionMethod,
            'Test signal description',
        );

        // Act & Assert - Initially empty
        self::assertSame([], $prototype->getSignalHandlers());

        // Act - Add signal handler
        $prototype->addSignalHandler($signalDefinition);

        // Assert
        $handlers = $prototype->getSignalHandlers();
        self::assertCount(1, $handlers);
        self::assertArrayHasKey('testSignal', $handlers);
        self::assertSame($signalDefinition, $handlers['testSignal']);
    }

    public function testAddMultipleSignalHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstSignal = new SignalDefinition(
            'firstSignal',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            $this->reflectionMethod,
            'First signal',
        );
        $secondSignal = new SignalDefinition(
            'secondSignal',
            HandlerUnfinishedPolicy::Abandon,
            $this->reflectionMethod,
            'Second signal',
        );

        // Act
        $prototype->addSignalHandler($firstSignal);
        $prototype->addSignalHandler($secondSignal);

        // Assert
        $handlers = $prototype->getSignalHandlers();
        self::assertCount(2, $handlers);
        self::assertArrayHasKey('firstSignal', $handlers);
        self::assertArrayHasKey('secondSignal', $handlers);
        self::assertSame($firstSignal, $handlers['firstSignal']);
        self::assertSame($secondSignal, $handlers['secondSignal']);
    }

    #[DataProvider('provideInvalidSignalNames')]
    public function testAddSignalHandlerThrowsExceptionForInvalidNames(string $invalidName): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $signalDefinition = new SignalDefinition(
            $invalidName,
            HandlerUnfinishedPolicy::WarnAndAbandon,
            $this->reflectionMethod,
            'Test signal description',
        );

        // Assert (before Act for exceptions)
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $prototype->addSignalHandler($signalDefinition);
    }

    public function testAddAndGetUpdateHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $updateDefinition = new UpdateDefinition(
            'testUpdate',
            'Test update description',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            'string',
            $this->reflectionMethod,
            null,
        );

        // Act & Assert - Initially empty
        self::assertSame([], $prototype->getUpdateHandlers());

        // Act - Add update handler
        $prototype->addUpdateHandler($updateDefinition);

        // Assert
        $handlers = $prototype->getUpdateHandlers();
        self::assertCount(1, $handlers);
        self::assertArrayHasKey('testUpdate', $handlers);
        self::assertSame($updateDefinition, $handlers['testUpdate']);
    }

    public function testAddMultipleUpdateHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstUpdate = new UpdateDefinition(
            'firstUpdate',
            'First update',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            'string',
            $this->reflectionMethod,
            null,
        );
        $secondUpdate = new UpdateDefinition(
            'secondUpdate',
            'Second update',
            HandlerUnfinishedPolicy::Abandon,
            'int',
            $this->reflectionMethod,
            $this->reflectionMethod,
        );

        // Act
        $prototype->addUpdateHandler($firstUpdate);
        $prototype->addUpdateHandler($secondUpdate);

        // Assert
        $handlers = $prototype->getUpdateHandlers();
        self::assertCount(2, $handlers);
        self::assertArrayHasKey('firstUpdate', $handlers);
        self::assertArrayHasKey('secondUpdate', $handlers);
        self::assertSame($firstUpdate, $handlers['firstUpdate']);
        self::assertSame($secondUpdate, $handlers['secondUpdate']);
    }

    #[DataProvider('provideInvalidUpdateNames')]
    public function testAddUpdateHandlerThrowsExceptionForInvalidNames(string $invalidName): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $updateDefinition = new UpdateDefinition(
            $invalidName,
            'Test update description',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            'string',
            $this->reflectionMethod,
            null,
        );

        // Assert (before Act for exceptions)
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $prototype->addUpdateHandler($updateDefinition);
    }

    public function testAddAndGetValidateUpdateHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $validatorMethod = $this->reflectionMethod;

        // Act & Assert - Initially empty
        self::assertSame([], $prototype->getValidateUpdateHandlers());

        // Act - Add validator
        $prototype->addValidateUpdateHandler('testValidator', $validatorMethod);

        // Assert
        $validators = $prototype->getValidateUpdateHandlers();
        self::assertCount(1, $validators);
        self::assertArrayHasKey('testValidator', $validators);
        self::assertSame($validatorMethod, $validators['testValidator']);
    }

    public function testAddMultipleValidateUpdateHandlers(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstValidator = $this->reflectionMethod;
        $secondValidator = $this->reflectionClass->getMethod('testAddMultipleValidateUpdateHandlers');

        // Act
        $prototype->addValidateUpdateHandler('firstValidator', $firstValidator);
        $prototype->addValidateUpdateHandler('secondValidator', $secondValidator);

        // Assert
        $validators = $prototype->getValidateUpdateHandlers();
        self::assertCount(2, $validators);
        self::assertArrayHasKey('firstValidator', $validators);
        self::assertArrayHasKey('secondValidator', $validators);
        self::assertSame($firstValidator, $validators['firstValidator']);
        self::assertSame($secondValidator, $validators['secondValidator']);
    }

    public function testQueryHandlerOverwritesPreviousWithSameName(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstQuery = new QueryDefinition(
            'duplicateName',
            'string',
            $this->reflectionMethod,
            'First query',
        );
        $secondQuery = new QueryDefinition(
            'duplicateName',
            'int',
            $this->reflectionMethod,
            'Second query',
        );

        // Act
        $prototype->addQueryHandler($firstQuery);
        $prototype->addQueryHandler($secondQuery);

        // Assert
        $handlers = $prototype->getQueryHandlers();
        self::assertCount(1, $handlers);
        self::assertSame($secondQuery, $handlers['duplicateName']);
    }

    public function testSignalHandlerOverwritesPreviousWithSameName(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstSignal = new SignalDefinition(
            'duplicateName',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            $this->reflectionMethod,
            'First signal',
        );
        $secondSignal = new SignalDefinition(
            'duplicateName',
            HandlerUnfinishedPolicy::Abandon,
            $this->reflectionMethod,
            'Second signal',
        );

        // Act
        $prototype->addSignalHandler($firstSignal);
        $prototype->addSignalHandler($secondSignal);

        // Assert
        $handlers = $prototype->getSignalHandlers();
        self::assertCount(1, $handlers);
        self::assertSame($secondSignal, $handlers['duplicateName']);
    }

    public function testUpdateHandlerOverwritesPreviousWithSameName(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstUpdate = new UpdateDefinition(
            'duplicateName',
            'First update',
            HandlerUnfinishedPolicy::WarnAndAbandon,
            'string',
            $this->reflectionMethod,
            null,
        );
        $secondUpdate = new UpdateDefinition(
            'duplicateName',
            'Second update',
            HandlerUnfinishedPolicy::Abandon,
            'int',
            $this->reflectionMethod,
            $this->reflectionMethod,
        );

        // Act
        $prototype->addUpdateHandler($firstUpdate);
        $prototype->addUpdateHandler($secondUpdate);

        // Assert
        $handlers = $prototype->getUpdateHandlers();
        self::assertCount(1, $handlers);
        self::assertSame($secondUpdate, $handlers['duplicateName']);
    }

    public function testValidateUpdateHandlerOverwritesPreviousWithSameName(): void
    {
        // Arrange
        $prototype = new WorkflowPrototype('TestWorkflow', null, $this->reflectionClass);
        $firstValidator = $this->reflectionMethod;
        $secondValidator = $this->reflectionClass->getMethod('testValidateUpdateHandlerOverwritesPreviousWithSameName');

        // Act
        $prototype->addValidateUpdateHandler('duplicateName', $firstValidator);
        $prototype->addValidateUpdateHandler('duplicateName', $secondValidator);

        // Assert
        $validators = $prototype->getValidateUpdateHandlers();
        self::assertCount(1, $validators);
        self::assertSame($secondValidator, $validators['duplicateName']);
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->reflectionClass = new \ReflectionClass(self::class);
        $this->reflectionMethod = $this->reflectionClass->getMethod(__FUNCTION__);
    }
}
