<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\WorkflowContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\FeatureFlags;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Workflow\WorkflowExecution;

#[CoversClass(WorkflowContext::class)]
#[CoversClass(ExecuteChildWorkflow::class)]
final class GeneratedChildWorkflowIdTestCase extends TestCase
{
    private WorkflowContext $context;
    private QueueInterface $queue;
    private bool $generateChildWorkflowIds;

    public function testSentChildrenGetRunScopedSequence(): void
    {
        self::assertSame('run-id_1', $this->sent(self::child())->getWorkflowId());
        self::assertSame('run-id_2', $this->sent(self::child())->getWorkflowId());
    }

    public function testChildIdIsLeftToRoadRunnerWhenFlagIsDisabled(): void
    {
        FeatureFlags::$generateChildWorkflowIds = false;

        $sent = $this->sent(self::child());

        self::assertNull($sent->getWorkflowId());
        self::assertNull($sent->getOptions()['options']['WorkflowID'] ?? null);
    }

    public function testGeneratedIdKeepsRequestIdentityAndOtherOptions(): void
    {
        $request = self::child(['TaskQueueName' => 'queue']);

        $generated = $this->sent($request);

        self::assertNotSame($request, $generated);
        self::assertNull($request->getWorkflowId());
        self::assertSame($request->getID(), $generated->getID());
        self::assertSame(
            ['TaskQueueName' => 'queue', 'WorkflowID' => 'run-id_1'],
            $generated->getOptions()['options'],
        );
    }

    public function testChildWithExplicitIdIsKeptAndDoesNotConsumeSequence(): void
    {
        $explicit = self::child(['WorkflowID' => 'explicit']);

        self::assertSame($explicit, $this->context->withSentChildWorkflowId($explicit));
        self::assertSame('run-id_1', $this->sent(self::child())->getWorkflowId());
    }

    public function testEmptyIdIsTreatedAsMissing(): void
    {
        self::assertSame('run-id_1', $this->sent(self::child(['WorkflowID' => '']))->getWorkflowId());
    }

    public function testOtherRequestsAreKept(): void
    {
        $request = new UpsertSearchAttributes(['attribute' => 'value']);

        self::assertSame($request, $this->context->withGeneratedChildWorkflowId($request));
        self::assertSame($request, $this->context->withSentChildWorkflowId($request));
    }

    public function testProvisionalIdsFollowPendingRequestsAndBecomeFinalInSendOrder(): void
    {
        $outer = $this->context->withGeneratedChildWorkflowId(self::child());
        $inner = $this->context->withGeneratedChildWorkflowId(self::child());
        self::assertSame('run-id_1', $outer->getWorkflowId());
        self::assertSame('run-id_2', $inner->getWorkflowId());

        $this->queued($this->context->withSentChildWorkflowId($inner));
        $this->queued($this->context->withSentChildWorkflowId($outer));

        self::assertSame('run-id_1', $inner->getWorkflowId());
        self::assertSame('run-id_2', $outer->getWorkflowId());
    }

    public function testReleasingLastSentIdReusesItsNumber(): void
    {
        $canceled = $this->sent(self::child());
        $this->queue->pull($canceled->getID());

        $this->context->releaseGeneratedChildWorkflowId($canceled);

        self::assertSame('run-id_1', $this->sent(self::child())->getWorkflowId());
    }

    public function testReleasingOlderIdRenumbersLaterChildrenAndTheirClones(): void
    {
        $canceled = $this->sent(self::child());
        $second = $this->sent(self::child());
        $third = $this->sent(self::child());
        $secondClone = $second->withHeader(Header::empty());
        $this->queue->pull($canceled->getID());

        $this->context->releaseGeneratedChildWorkflowId($canceled);

        self::assertSame('run-id_1', $second->getWorkflowId());
        self::assertSame('run-id_1', $secondClone->getWorkflowId());
        self::assertSame('run-id_1', $secondClone->getOptions()['options']['WorkflowID']);
        self::assertSame('run-id_2', $third->getWorkflowId());
        self::assertSame('run-id_3', $this->sent(self::child())->getWorkflowId());
    }

    public function testSentChildIdsAreForgottenAndNotReleased(): void
    {
        $flushed = $this->sent(self::child());
        $this->queue->pull($flushed->getID());
        $this->sent(self::child());

        $this->context->releaseGeneratedChildWorkflowId($flushed);

        self::assertSame('run-id_3', $this->sent(self::child())->getWorkflowId());
    }

    public function testManyQueuedChildrenAreNumberedInLinearTime(): void
    {
        $started = \microtime(true);
        for ($i = 0; $i < 3000; ++$i) {
            $last = $this->sent(self::child());
        }

        self::assertSame('run-id_3000', $last->getWorkflowId());
        self::assertLessThan(2.0, \microtime(true) - $started);
    }

    public function testReleasingRenumberedChildKeepsSequenceConsistent(): void
    {
        $first = $this->sent(self::child());
        $second = $this->sent(self::child());
        $this->queue->pull($first->getID());
        $this->queue->pull($second->getID());

        $this->context->releaseGeneratedChildWorkflowId($first);
        $this->context->releaseGeneratedChildWorkflowId($second);

        self::assertSame('run-id_1', $this->sent(self::child())->getWorkflowId());
    }

    public function testReleasingUnknownRequestsKeepsSequence(): void
    {
        $this->sent(self::child());

        $this->context->releaseGeneratedChildWorkflowId(self::child(['WorkflowID' => 'explicit']));
        $this->context->releaseGeneratedChildWorkflowId(self::child());
        $this->context->releaseGeneratedChildWorkflowId(new UpsertSearchAttributes(['attribute' => 'value']));

        self::assertSame('run-id_2', $this->sent(self::child())->getWorkflowId());
    }

    public function testClonedContextSharesSequence(): void
    {
        $clone = $this->context->withInput(new Input($this->context->getInfo()));

        $this->sent(self::child());

        self::assertSame('run-id_2', $this->queued($clone->withSentChildWorkflowId(self::child()))->getWorkflowId());
    }

    public function testRequestSentThroughContextGetsId(): void
    {
        $this->context->setReadonly(false);

        $this->context->request(self::child(), waitResponse: false);

        $sent = \iterator_to_array($this->queue, false);
        self::assertCount(1, $sent);
        self::assertSame('run-id_1', $sent[0]->getWorkflowId());
    }

    protected function setUp(): void
    {
        $this->generateChildWorkflowIds = FeatureFlags::$generateChildWorkflowIds;
        FeatureFlags::$generateChildWorkflowIds = true;
        $factory = new WorkerFactoryMock(DataConverter::createDefault());
        $this->queue = $factory->getQueue();
        $services = ServiceContainer::fromWorkerFactory(
            $factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider(),
            new StderrLogger(),
        );
        $workflow = new class {
            public function handle(): void {}
        };
        $reflection = new \ReflectionClass($workflow);
        $instance = new WorkflowInstance(
            new WorkflowPrototype('Workflow', $reflection->getMethod('handle'), $reflection),
            $workflow,
        );
        $input = new Input();
        $input->info->execution = new WorkflowExecution('workflow-id', 'run-id');

        $this->context = new WorkflowContext($services, $services->client, $instance, $input, EncodedValues::empty());

        parent::setUp();
    }

    protected function tearDown(): void
    {
        FeatureFlags::$generateChildWorkflowIds = $this->generateChildWorkflowIds;

        parent::tearDown();
    }

    private function sent(ExecuteChildWorkflow $request): ExecuteChildWorkflow
    {
        $sent = $this->context->withSentChildWorkflowId($request);
        \assert($sent instanceof ExecuteChildWorkflow);

        return $this->queued($sent);
    }

    private function queued(ExecuteChildWorkflow $request): ExecuteChildWorkflow
    {
        $this->queue->push($request);

        return $request;
    }

    private static function child(array $options = []): ExecuteChildWorkflow
    {
        return new ExecuteChildWorkflow('Child', EncodedValues::empty(), $options, Header::empty());
    }
}
