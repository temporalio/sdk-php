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
    }
}

class WorkerServices
{
    public static function interceptors(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new ActivityInboundInterceptor(),
        ]);
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

#[WorkflowInterface]
class TestWorkflow
{
    private array $result = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Interceptors_Context")]
    public function handle()
    {
        $activityClass = yield Workflow::executeActivity(
            'Extra_Interceptors_Context.handler',
            ['foo'],
            Activity\ActivityOptions::new()->withScheduleToCloseTimeout('10 seconds'),
        );
        yield Workflow::await(fn() => $this->exit);
        return [
            'activity' => $activityClass,
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
