<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Interceptors\Init;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\InitInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(pipelineProvider: [WorkerServices::class, 'interceptors'])]
class InitTest extends TestCase
{
    #[Test]
    public function initIsCalledBeforeExecute(
        #[Stub('Extra_Interceptors_Init')] WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('array');

        self::assertTrue($result['initCalled'], 'init() interceptor was not called');
        self::assertSame(1, $result['initCount'], 'init() interceptor was called more than once per workflow instance');
        self::assertSame('Extra_Interceptors_Init', $result['initType'], 'InitInput did not carry the correct workflow type');
        self::assertTrue($result['staticContextAvailable'], 'Workflow static context was not available in init()');
    }

    #[Test]
    public function failInInit(
        #[Stub('Extra_Interceptors_Init_Failing')] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->getResult('array');
            $this->fail('An exception should have been thrown.');
        } catch (WorkflowFailedException $e) {
            $prev = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $prev);
            self::assertStringContainsString('exception-in-init', $prev->getOriginalMessage());
        }
    }
}

class WorkerServices
{
    public static function interceptors(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new WorkflowInboundInterceptor(),
        ]);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    public bool $initCalled = false;
    public int $initCount = 0;
    public string $initType = '';
    public bool $staticContextAvailable = false;

    #[WorkflowMethod(name: 'Extra_Interceptors_Init')]
    public function handle(): array
    {
        return [
            'initCalled' => $this->initCalled,
            'initCount' => $this->initCount,
            'initType' => $this->initType,
            'staticContextAvailable' => $this->staticContextAvailable,
        ];
    }
}

#[WorkflowInterface]
class TestFailingInInitWorkflow
{
    #[WorkflowMethod(name: 'Extra_Interceptors_Init_Failing')]
    public function handle(): array
    {
        return [];
    }
}

final class WorkflowInboundInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    public function init(InitInput $input, callable $next): void
    {
        if ($input->info->type->name === 'Extra_Interceptors_Init_Failing') {
            throw new ApplicationFailure('exception-in-init', 'error', true);
        }

        $instance = Workflow::getInstance();
        if ($instance instanceof TestWorkflow) {
            $instance->initCalled = true;
            ++$instance->initCount;
            $instance->initType = $input->info->type->name;
            // Verify that Workflow:: static context is available in init()
            $instance->staticContextAvailable = Workflow::getInfo()->type->name === $input->info->type->name;
        }

        $next($input);
    }
}
