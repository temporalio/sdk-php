<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Transport\Request\SideEffect;
use Temporal\Internal\Workflow\Input;
use Temporal\Internal\Workflow\Process\Process;
use Temporal\Worker\Transport\Command\Request;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContext;
use Temporal\Workflow\WorkflowInfo;
use Temporal\Tests\Testing\TestingRequest;

class ProcessTestCase extends WorkflowTestCase
{
    /**
     * @var Input
     */
    protected Input $input;

    /**
     * @throws \Exception
     */
    public function testSendRequest(): void
    {
        $request = new Request(\random_bytes(42));

        $this->workflow(function () use ($request) {
            yield $request;
        });

        $this->queue->assertRequestsCount(1);

        $this->queue->each(function (RequestInterface $current) use ($request) {
            $this->assertSame($request->getID(), $current->getID());
            $this->assertSame($request->getName(), $current->getName());
            $this->assertSame($request->getOptions(), $current->getOptions());
        });
    }

    /**
     * @param \Closure $handler
     * @return Process
     * @throws \ReflectionException
     */
    protected function workflow(\Closure $handler): Process
    {
        try {
            $prototype = new WorkflowPrototype(static::class,
                new \ReflectionFunction($handler),
                new \ReflectionObject($this)
            );

            $instance = new WorkflowInstance($prototype, DataConverter::createDefault(), $this);

            $context = new WorkflowContext(
                $this->services,
                $this->services->client,
                $instance,
                new Input()
            );

            $p = new Process($this->services, $context);
            $p->start($instance->getHandler(), []);

            return $p;
        } finally {
            $this->loop->tick();
        }
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testExecuteActivity(): void
    {
        $this->workflow(function () {
            yield Workflow::executeActivity('ExampleActivity', [0xDEAD_BEEF]);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertName(ExecuteActivity::NAME);

        $request->assertParamsKeySame('name', 'ExampleActivity');
        $request->assertParamsKeySame('arguments', [0xDEAD_BEEF]);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testExecuteActivityWithoutOptions(): void
    {
        $options = $this->marshal(new ActivityOptions());

        $this->workflow(function () {
            yield Workflow::executeActivity('ExampleActivity', []);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertParamsKeySame('options', $options);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testExecuteActivityWithArrayOptions(): void
    {
        $options = new ActivityOptions();
        $options->taskQueue = \random_bytes(42);

        $this->workflow(function () use ($options) {
            yield Workflow::executeActivity('ExampleActivity', [], $options);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertParamsKeySame('options', $this->marshal($options));
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testExecuteActivityWithObjectOptions(): void
    {
        $options = new ActivityOptions();
        $options->taskQueue = \random_bytes(42);

        $this->workflow(function () use ($options) {
            yield Workflow::executeActivity('ExampleActivity', [], $options);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertParamsKeySame('options', $this->marshal($options));
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testTimer(): void
    {
        $seconds = \random_int(1, 9_999_999);

        $this->workflow(function () use ($seconds) {
            yield Workflow::timer($seconds);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertName(NewTimer::NAME);
        $request->assertParamsKeySame('ms', CarbonInterval::seconds($seconds)->totalMilliseconds);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testSideEffectDuringFirstWorkflowExecution(): void
    {
        $value = \base64_encode(\random_bytes(42));

        $this->env->setIsReplaying(false);

        $this->workflow(function () use ($value) {
            yield Workflow::sideEffect(fn() => $value);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertName(SideEffect::NAME);
        $request->assertParamsKeySame('value', $value);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testSideEffectDuringWorkflowReplication(): void
    {
        $value = \random_bytes(42);

        $this->env->setIsReplaying(true);

        $this->workflow(function () use ($value) {
            yield Workflow::sideEffect(fn() => $value);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertName(SideEffect::NAME);
        $request->assertParamsKeySame('value', null);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testGetVersion(): void
    {
        $changeId = \random_bytes(42);
        $min = \random_int(1, 9_999_999);
        $max = \random_int(1, 9_999_999);

        $this->workflow(function () use ($changeId, $min, $max) {
            yield Workflow::getVersion($changeId, $min, $max);
        });

        /** @var TestingRequest $request */
        $request = $this->queue->first();

        $request->assertName(GetVersion::NAME);
        $request->assertParamsKeySame('changeID', $changeId);
        $request->assertParamsKeySame('minSupported', $min);
        $request->assertParamsKeySame('maxSupported', $max);
    }

    public function testCancellationScope(): void
    {
        $this->workflow(function () {
            $id = Workflow::getContextId();

            yield new Request('First Request');
            $this->assertSame($id, Workflow::getContextId());

            $scopeResult = yield Workflow::newCancellationScope(function () use ($id) {
                $this->assertNotSame($scoped = Workflow::getContextId(), $id);

                yield new Request('First Scoped Request');
                $this->assertSame($scoped, Workflow::getContextId());

                yield new Request('Second Scoped Request');
                $this->assertSame($scoped, Workflow::getContextId());

                return 0xDEAD_BEEF;
            });

            $this->assertSame(0xDEAD_BEEF, $scopeResult);

            $this->assertSame($id, Workflow::getContextId());
            yield new Request('Second Request');
            $this->assertSame($id, Workflow::getContextId());

            return 42;
        });

        /** @var TestingRequest $request */
        $request = $this->queue->assertCount(1)
            ->pop()
                ->assertName('First Request');
        $this->successResponseAndNext($request, 'First Request');


        /** @var TestingRequest $request */
        $request = $this->queue->assertCount(1)
            ->pop()
                ->assertName('First Scoped Request');
        $this->successResponseAndNext($request, 'First Scoped Request');


        /** @var TestingRequest $request */
        $request = $this->queue->assertCount(1)
            ->pop()
                ->assertName('Second Scoped Request');
        $this->successResponseAndNext($request, 'Second Scoped Request');


        $this->queue->assertCount(1);

        $request = $this->queue
            ->pop()
                ->assertName('Second Request');

        $this->successResponseAndNext($request, 'Second Request');

        $this->queue->assertCount(1)
            ->pop()
                ->assertName(CompleteWorkflow::NAME)
                ->assertParamsKeySame('result', [42]);
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->input = new Input(new WorkflowInfo());
    }
}
