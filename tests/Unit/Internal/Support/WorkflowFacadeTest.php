<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temporal\Exception\OutOfContextException;
use Temporal\Workflow;

class WorkflowFacadeTest extends TestCase
{
    /**
     * @return iterable<string, array{callable, string}>
     */
    public static function outOfContextMethods(): iterable
    {
        yield 'getCurrentContext' => [
            static fn() => Workflow::getCurrentContext(),
        ];

        yield 'now' => [
            static fn() => Workflow::now(),
        ];

        yield 'getInfo' => [
            static fn() => Workflow::getInfo(),
        ];

        yield 'getInput' => [
            static fn() => Workflow::getInput(),
        ];

        yield 'await' => [
            static fn() => Workflow::await(static fn() => true),
        ];

        yield 'awaitWithTimeout' => [
            static fn() => Workflow::awaitWithTimeout(1, static fn() => true),
        ];

        yield 'isReplaying' => [
            static fn() => Workflow::isReplaying(),
        ];

        yield 'getVersion' => [
            static fn() => Workflow::getVersion('test', Workflow::DEFAULT_VERSION, 1),
        ];

        yield 'timer' => [
            static fn() => Workflow::timer(1),
        ];

        yield 'executeChildWorkflow' => [
            static fn() => Workflow::executeChildWorkflow('Test'),
        ];

        yield 'newChildWorkflowStub' => [
            static fn() => Workflow::newChildWorkflowStub(\stdClass::class),
        ];

        yield 'newExternalWorkflowStub' => [
            static fn() => Workflow::newExternalWorkflowStub('test', new Workflow\WorkflowExecution()),
        ];

        yield 'continueAsNew' => [
            static fn() => Workflow::continueAsNew(''),
        ];

        yield 'async' => [
            static fn() => Workflow::async(static fn() => yield),
        ];

        yield 'asyncDetached' => [
            static fn() => Workflow::asyncDetached(static fn() => yield),
        ];

        yield 'newActivityStub' => [
            static fn() => Workflow::newActivityStub(\stdClass::class),
        ];

        yield 'newUntypedActivityStub' => [
            static fn() => Workflow::newUntypedActivityStub(),
        ];

        yield 'executeActivity' => [
            static fn() => Workflow::executeActivity('test'),
        ];

        yield 'getStackTrace' => [
            static fn() => Workflow::getStackTrace(),
        ];

        yield 'allHandlersFinished' => [
            static fn() => Workflow::allHandlersFinished(),
        ];

        yield 'upsertMemo' => [
            static fn() => Workflow::upsertMemo(['key' => 'value']),
        ];

        yield 'upsertSearchAttributes' => [
            static fn() => Workflow::upsertSearchAttributes(['key' => 'value']),
        ];

        yield 'uuid' => [
            static fn() => Workflow::uuid(),
        ];

        yield 'uuid4' => [
            static fn() => Workflow::uuid4(),
        ];

        yield 'uuid7' => [
            static fn() => Workflow::uuid7(),
        ];

        yield 'runLocked' => [
            static fn() => Workflow::runLocked(new \Temporal\Workflow\Mutex('test'), static fn() => yield),
        ];

        yield 'getLogger' => [
            static fn() => Workflow::getLogger(),
        ];
        yield 'getInstance' => [
            static fn() => Workflow::getInstance(),
        ];

        yield 'getUpdateContext' => [
            static fn() => Workflow::getUpdateContext(),
        ];

        yield 'getLastCompletionResult' => [
            static fn() => Workflow::getLastCompletionResult(),
        ];

        yield 'registerQuery' => [
            static fn() => Workflow::registerQuery('test', static fn() => null),
        ];

        yield 'registerSignal' => [
            static fn() => Workflow::registerSignal('test', static fn() => null),
        ];

        yield 'registerDynamicSignal' => [
            static fn() => Workflow::registerDynamicSignal(static fn() => null),
        ];

        yield 'registerDynamicQuery' => [
            static fn() => Workflow::registerDynamicQuery(static fn() => null),
        ];

        yield 'registerDynamicUpdate' => [
            static fn() => Workflow::registerDynamicUpdate(static fn() => null),
        ];

        yield 'registerUpdate' => [
            static fn() => Workflow::registerUpdate('test', static fn() => null),
        ];

        yield 'sideEffect' => [
            static fn() => Workflow::sideEffect(static fn() => null),
        ];

        yield 'newContinueAsNewStub' => [
            static fn() => Workflow::newContinueAsNewStub(\stdClass::class),
        ];

        yield 'newUntypedChildWorkflowStub' => [
            static fn() => Workflow::newUntypedChildWorkflowStub('test'),
        ];

        yield 'newUntypedExternalWorkflowStub' => [
            static fn() => Workflow::newUntypedExternalWorkflowStub(new Workflow\WorkflowExecution()),
        ];

        yield 'upsertTypedSearchAttributes' => [
            static fn() => Workflow::upsertTypedSearchAttributes(),
        ];
    }

    #[Test]
    #[DataProvider('outOfContextMethods')]
    public function throwsOutOfContextException(callable $method): void
    {
        $this->expectException(OutOfContextException::class);
        $this->expectExceptionMessage('The Workflow facade can be used only inside workflow code.');

        $method();
    }
}
