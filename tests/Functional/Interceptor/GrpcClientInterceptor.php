<?php

declare(strict_types=1);

namespace Interceptor;

use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Testing\TestService;
use Temporal\Tests\Workflow\SimpleWorkflow;

class GrpcClientInterceptor extends TestCase
{
    protected WorkflowClient $workflowClient;
    protected TestService $testingService;

    /** @var array<non-empty-string, object> */
    protected array $called = [];

    /**
     * @psalm-suppress MissingImmutableAnnotation
     */
    protected function setUp(): void
    {
        $temporalAddress = getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233';
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create($temporalAddress)
                ->withInterceptorPipeline(
                    Pipeline::prepare([
                        new class ($this->called) implements \Temporal\Interceptor\GrpcClientInterceptor {
                            private array $called;

                            public function __construct(array &$called)
                            {
                                $this->called = &$called;
                            }

                            public function interceptCall(
                                string $method,
                                object $arg,
                                ContextInterface $ctx,
                                callable $next,
                            ): object {
                                $this->called[$method] = $arg;
                                return $next($method, $arg, $ctx);
                            }
                        },
                    ]),
                )
        );
        $this->testingService = TestService::create($temporalAddress);

        parent::setUp();
    }

    public function testParentCanWaitForChildResult(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'foo');

        self::assertArrayHasKey('StartWorkflowExecution', $this->called);
        self::assertSame('FOO', $run->getResult());
    }
}
