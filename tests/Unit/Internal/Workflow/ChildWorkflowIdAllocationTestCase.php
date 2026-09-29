<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowOutboundRequestInterceptorTrait;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\FeatureFlags;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;

use function React\Promise\resolve;

#[CoversClass(ScopeContext::class)]
#[CoversClass(WorkflowContext::class)]
final class ChildWorkflowIdAllocationTestCase extends TestCase
{
    /** @var list<string> */
    private array $sentChildTypes = [];
    private bool $generateChildWorkflowIds;

    public static function provideInterceptorsThatDoNotSend(): iterable
    {
        yield 'short-circuit' => [
            static fn(RequestInterface $request, callable $next): PromiseInterface => resolve(null),
        ];
        yield 'throw' => [
            static fn(RequestInterface $request, callable $next): PromiseInterface => throw new \RuntimeException('blocked'),
        ];
    }

    protected function setUp(): void
    {
        $this->generateChildWorkflowIds = FeatureFlags::$generateChildWorkflowIds;
        FeatureFlags::$generateChildWorkflowIds = true;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        FeatureFlags::$generateChildWorkflowIds = $this->generateChildWorkflowIds;

        parent::tearDown();
    }

    #[DataProvider('provideInterceptorsThatDoNotSend')]
    public function testChildThatIsNotSentDoesNotConsumeSequence(\Closure $block): void
    {
        $queue = $this->startWorkflow(new SkippingInterceptor($block));

        self::assertSame(['run-id_1'], $this->sentChildIds($queue));
    }

    public function testChildSentLaterGetsTheNextSequenceWhenItIsSent(): void
    {
        $postponed = new \ArrayObject();
        $queue = $this->startWorkflow(new SkippingInterceptor(
            static function (RequestInterface $request, callable $next) use ($postponed): PromiseInterface {
                $postponed->append(static fn(): PromiseInterface => $next($request));
                return resolve(null);
            },
        ));

        ($postponed[0])();

        self::assertSame(['run-id_1', 'run-id_2'], $this->sentChildIds($queue));
        self::assertSame(['Sent', 'Skipped'], $this->sentChildTypes);
    }

    public function testChildStartedInsideInterceptorIsNumberedInSendOrder(): void
    {
        $queue = $this->startWorkflow(new SkippingInterceptor(
            static function (RequestInterface $request, callable $next): PromiseInterface {
                Workflow::newUntypedChildWorkflowStub('Inner')->start();
                return $next($request);
            },
        ));

        self::assertSame(['run-id_1', 'run-id_2', 'run-id_3'], $this->sentChildIds($queue));
        self::assertSame(['Inner', 'Skipped', 'Sent'], $this->sentChildTypes);
    }

    public function testYieldedChildRequestGetsId(): void
    {
        $queue = $this->startWorkflow(new SkippingInterceptor(null), new YieldingRequestWorkflow());

        self::assertSame(['run-id_1'], $this->sentChildIds($queue));
    }

    public function testEveryChildThatIsSentConsumesSequence(): void
    {
        $queue = $this->startWorkflow(new SkippingInterceptor(null));

        self::assertSame(['run-id_1', 'run-id_2'], $this->sentChildIds($queue));
    }

    private function startWorkflow(WorkflowOutboundRequestInterceptor $interceptor, ?object $workflow = null): iterable
    {
        $factory = new WorkerFactoryMock(DataConverter::createDefault());
        $services = ServiceContainer::fromWorkerFactory(
            $factory,
            ExceptionInterceptor::createDefault(),
            new SimplePipelineProvider([$interceptor]),
            new StderrLogger(),
        );

        $workflow ??= new TwoChildrenWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $instance = new WorkflowInstance(
            new WorkflowPrototype('TwoChildren', $reflection->getMethod('handle'), $reflection),
            $workflow,
        );
        $input = new Input();
        $input->info->execution = new WorkflowExecution('workflow-id', 'run-id');
        $context = new WorkflowContext($services, $services->client, $instance, $input, EncodedValues::empty());

        (new Process($services, 'run-id', $instance))->initAndStart($context, $instance, false);
        Workflow::setCurrentContext(null);

        return $factory->getQueue();
    }

    /**
     * @return list<string|null>
     */
    private function sentChildIds(iterable $queue): array
    {
        $ids = [];
        foreach ($queue as $command) {
            if ($command instanceof ExecuteChildWorkflow) {
                $ids[] = $command->getOptions()['options']['WorkflowID'] ?? null;
                $this->sentChildTypes[] = $command->getWorkflowType();
            }
        }

        return $ids;
    }
}

final class SkippingInterceptor implements WorkflowOutboundRequestInterceptor
{
    use WorkflowOutboundRequestInterceptorTrait;

    public function __construct(
        private readonly ?\Closure $block,
    ) {}

    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        if ($this->block !== null && $request instanceof ExecuteChildWorkflow && $request->getWorkflowType() === 'Skipped') {
            return ($this->block)($request, $next);
        }

        return $next($request);
    }
}

final class TwoChildrenWorkflow
{
    public function handle(): \Generator
    {
        try {
            Workflow::newUntypedChildWorkflowStub('Skipped')->start();
        } catch (\RuntimeException) {
        }

        Workflow::newUntypedChildWorkflowStub('Sent')->start();

        yield Workflow::await(static fn(): bool => false);
    }
}

final class YieldingRequestWorkflow
{
    public function handle(): \Generator
    {
        yield new ExecuteChildWorkflow('Yielded', EncodedValues::empty(), [], Header::empty());
    }
}
