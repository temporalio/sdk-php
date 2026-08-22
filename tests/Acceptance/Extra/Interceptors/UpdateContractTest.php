<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Interceptors\UpdateContract;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(pipelineProvider: [WorkerServices::class, 'interceptors'])]
class UpdateContractTest extends TestCase
{
    #[Test]
    public function interceptorObservesResolvedUpdateResult(
        #[Stub('Extra_Interceptors_UpdateContract')] WorkflowStubInterface $stub,
    ): void {
        $handle = $stub->startUpdate('suspendingUpdate', 'payload');

        $stub->signal('release');

        self::assertSame('handled:payload', $handle->getResult());

        $stub->signal('exit');

        self::assertSame(
            ['string:handled:payload'],
            $stub->getResult('array'),
        );
    }
}

class WorkerServices
{
    public static function interceptors(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new UpdateResultRecordingInterceptor(),
        ]);
    }
}

class UpdateResultRecordingInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    public function handleUpdate(UpdateInput $input, callable $next): mixed
    {
        $result = $next($input);

        Workflow::getInstance()->record(\get_debug_type($result) . ':' . $result);

        return $result;
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;
    private bool $released = false;

    /** @var list<string> */
    private array $observed = [];

    #[WorkflowMethod(name: 'Extra_Interceptors_UpdateContract')]
    public function handle(): array
    {
        Workflow::await(fn(): bool => $this->exit);

        return $this->observed;
    }

    public function record(string $value): void
    {
        $this->observed[] = $value;
    }

    #[Workflow\UpdateMethod(name: 'suspendingUpdate')]
    public function suspendingUpdate(string $value): string
    {
        Workflow::await(fn(): bool => $this->released);

        return 'handled:' . $value;
    }

    #[Workflow\SignalMethod(name: 'release')]
    public function release(): void
    {
        $this->released = true;
    }

    #[Workflow\SignalMethod(name: 'exit')]
    public function exit(): void
    {
        $this->exit = true;
    }
}
