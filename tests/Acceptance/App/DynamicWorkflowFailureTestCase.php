<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use React\Promise\PromiseInterface;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowOutboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowOutboundCalls\CompleteInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Workflow;

abstract class DynamicWorkflowFailureTestCase extends TestCase
{
    public static function pipelineProvider(): SimplePipelineProvider
    {
        return new SimplePipelineProvider([
            new class implements WorkflowOutboundCallsInterceptor {
                use WorkflowOutboundCallsInterceptorTrait;

                public function complete(CompleteInput $input, callable $next): PromiseInterface
                {
                    if ($input->failure !== null) {
                        $cause = $input->failure->getPrevious();
                        Workflow::getLogger()->error('Dynamic workflow handler failed', [
                            'exception' => $input->failure::class,
                            'cause' => $cause === null ? null : $cause::class,
                            'message' => $input->failure->getMessage(),
                        ]);
                    }

                    return $next($input);
                }
            },
        ]);
    }

    protected function assertHandlerFailure(
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        ClientLogger $logger,
        string $message,
        string $cause = \TypeError::class,
    ): void {
        try {
            $stub->getResult(timeout: 5);
            self::fail('An invalid dynamic handler must fail the workflow.');
        } catch (WorkflowFailedException $exception) {
            $failure = $exception->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertSame(InvalidArgumentException::class, $failure->getType());
            self::assertSame($message, $failure->getOriginalMessage());
            self::assertInstanceOf(ApplicationFailure::class, $failure->getPrevious());
            self::assertSame($cause, $failure->getPrevious()->getType());
        }

        $records = $logger->findByMessage('/^Dynamic workflow handler failed$/');
        self::assertCount(1, $records);
        self::assertSame('error', $records[0]->level);
        self::assertSame(InvalidArgumentException::class, $records[0]->context['exception']);
        self::assertSame($cause, $records[0]->context['cause']);
        self::assertSame($failure->getOriginalMessage(), $records[0]->context['message']);

        $events = [];
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            $events[] = $event->getEventType();
        }
        self::assertContains(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED, $events);
        self::assertNotContains(EventType::EVENT_TYPE_WORKFLOW_TASK_FAILED, $events);
    }
}
