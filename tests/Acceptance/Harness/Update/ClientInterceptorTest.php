<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\ClientInterceptor;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\StartUpdateOutput;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\UpdateMethod;

class ClientInterceptorTest extends TestCase
{
    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([new Interceptor()]);
    }

    #[Test]
    public static function check(
        #[Stub('Harness_Update_ClientInterceptor')]
        #[Client(pipelineProvider: [self::class, 'pipelineProvider'])]
        WorkflowStubInterface $stub,
    ): void {
        $updated = $stub->update('my_update', 1)->getValue(0);
        self::assertSame(2, $updated);
        $stub->getResult();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;

    #[WorkflowMethod('Harness_Update_ClientInterceptor')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->done);
        return 'Hello, World!';
    }

    #[UpdateMethod('my_update')]
    public function myUpdate(int $arg): int
    {
        $this->done = true;
        return $arg;
    }
}

class Interceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public function update(UpdateInput $input, callable $next): StartUpdateOutput
    {
        if ($input->updateName !== 'my_update') {
            return $next($input);
        }

        $rg = $input->arguments->getValue(0);

        return $next($input->with(arguments: EncodedValues::fromValues([$rg + 1])));
    }
}
