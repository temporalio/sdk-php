<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Interceptors\Context;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(pipelineProvider: [WorkerServices::class, 'interceptors'])]
class ContextTest extends TestCase
{
    #[Test]
    public function instanceInContext(
        #[Stub('Extra_Interceptors_Context')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('exit');
        $result = $stub->getResult('array');
        self::assertSame(TestActivity::class, $result['activity']);
        self::assertSame(TestWorkflow::class, $result['workflow']);
        self::assertTrue($result['assert'], 'Workflow instance in context is not the same as the one in the test');
    }
}

class WorkerServices
{
    public static function interceptors(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new ActivityInboundInterceptor(),
            new WorkflowInboundInterceptor(),
        ]);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    private array $result = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Interceptors_Context")]
    public function handle(string $class)
    {
        $activityClass = yield Workflow::executeActivity(
            'Extra_Interceptors_Context.handler',
            ['foo'],
            Activity\ActivityOptions::new()->withScheduleToCloseTimeout('10 seconds'),
        );
        yield Workflow::await(fn() => $this->exit);
        return [
            'activity' => $activityClass,
            'workflow' => $class,
            'assert' => Workflow::getInstance() === $this,
        ];
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}

#[Activity\ActivityInterface(prefix: 'Extra_Interceptors_Context.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function handler(string $result): string
    {
        return $result;
    }
}

final class WorkflowInboundInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    public function execute(WorkflowInput $input, callable $next): void
    {
        $next($input->with(arguments: EncodedValues::fromValues([Workflow::getInstance()::class])));
    }
}

final class ActivityInboundInterceptor implements \Temporal\Interceptor\ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait;

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $instance = Activity::getInstance();
        $input = $input->with(
            arguments: EncodedValues::fromValues([$instance::class]),
        );
        return $next($input);
    }
}
