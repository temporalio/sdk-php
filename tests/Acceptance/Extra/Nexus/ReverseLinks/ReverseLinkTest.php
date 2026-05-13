<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\ReverseLinks;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Handler's WorkflowExecutionStarted event carries a CompletionCallback link
 * back to the caller. Server-set; requires Temporal 1.27+ to populate the slot.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class ReverseLinkTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function handlerWorkflowExecutionStartedCarriesCallbackLinkBackToCaller(
        State $state,
        WorkflowClientInterface $client,
        #[Stub('Extra_Nexus_ReverseLinks_Bootstrap')]
        WorkflowStubInterface $bootstrapStub,
    ): void {
        $bootstrapStub->getResult('string');

        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-reverse-link',
        );

        $callerStub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_ReverseLinks_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30)),
        );

        $client->start($callerStub, $endpoint['name']);

        self::assertSame('done-reverse-link', $callerStub->getResult('string'));

        $callerExecution = $callerStub->getExecution();
        $handlerExecution = self::lookupHandlerExecution($client, $callerExecution);

        $handlerHistory = $client->getWorkflowHistory($handlerExecution)->getHistory();
        $startedAttributes = null;
        foreach ($handlerHistory->getEvents() as $event) {
            if ($event->getEventType() === EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED) {
                $startedAttributes = $event->getWorkflowExecutionStartedEventAttributes();
                break;
            }
        }
        self::assertNotNull($startedAttributes, 'Handler workflow must have a WORKFLOW_EXECUTION_STARTED event.');

        $callbacks = $startedAttributes->getCompletionCallbacks();
        self::assertGreaterThan(
            0,
            $callbacks->count(),
            'Handler workflow must have at least one completion callback on its started event.',
        );

        $matched = false;
        foreach ($callbacks as $callback) {
            foreach ($callback->getLinks() as $link) {
                $workflowEvent = $link->getWorkflowEvent();
                if ($workflowEvent === null) {
                    continue;
                }
                if (
                    $workflowEvent->getNamespace() === $state->namespace
                    && $workflowEvent->getWorkflowId() === $callerExecution->getID()
                ) {
                    $matched = true;
                    break 2;
                }
            }
        }
        self::assertTrue(
            $matched,
            'Handler started event must carry a CompletionCallback link with a workflow_event '
            . 'pointing to the caller workflow ' . $callerExecution->getID() . '.',
        );
    }

    private static function lookupHandlerExecution(
        WorkflowClientInterface $client,
        WorkflowExecution $callerExecution,
    ): WorkflowExecution {
        $callerHistory = $client->getWorkflowHistory($callerExecution)->getHistory();
        foreach ($callerHistory->getEvents() as $event) {
            if ($event->getEventType() !== EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED) {
                continue;
            }
            $startedEventAttributes = $event->getNexusOperationStartedEventAttributes();
            $token = $startedEventAttributes?->getOperationToken() ?? '';
            self::assertNotSame('', $token, 'Caller history must carry a non-empty operation token.');
            $decoded = \json_decode(\base64_decode(\strtr($token, '-_', '+/')), true);
            self::assertIsArray($decoded, "Operation token did not decode as JSON: {$token}");
            $workflowId = $decoded['wid'] ?? null;
            self::assertIsString($workflowId, "Operation token JSON missing 'wid': " . \var_export($decoded, true));

            // The token only carries the workflow id; describe the workflow to learn its run id,
            // which getWorkflowHistory requires (see WorkflowClient::getWorkflowHistory).
            $handlerStub = $client->newUntypedRunningWorkflowStub($workflowId);
            return $handlerStub->describe()->info->execution;
        }
        self::fail('Caller history did not contain a NEXUS_OPERATION_STARTED event; cannot derive handler workflow id.');
    }
}

#[Service(name: 'ReverseLinkService')]
class ReverseLinkService
{
    #[AsyncOperation(output: 'string')]
    public function backedByWorkflow(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                ReverseLinkHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'backedByWorkflow')]
    public function cancelBackedByWorkflow(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class ReverseLinkHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ReverseLinks_Handler')]
    public function handle(string $input)
    {
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        return 'done-' . $input;
    }
}

#[WorkflowInterface]
class ReverseLinkCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ReverseLinks_Caller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            ReverseLinkService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20)),
        );

        return yield $stub->backedByWorkflow('reverse-link');
    }
}

#[WorkflowInterface]
class ReverseLinksBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_ReverseLinks_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
