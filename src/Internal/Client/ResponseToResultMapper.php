<?php

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionResponse;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\WorkflowClient\StartUpdateOutput;
use Temporal\Interceptor\WorkflowClient\UpdateRef;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 */
final class ResponseToResultMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {}

    public function mapUpdateWorkflowResponse(
        UpdateWorkflowExecutionResponse $result,
        string $updateName,
        ?string $workflowType,
        WorkflowExecution $workflowExecution,
        string $namespace,
    ): StartUpdateOutput {
        $outcome = $result->getOutcome();
        $updateRef = $result->getUpdateRef();
        \assert($updateRef !== null);
        $updateRefDto = new UpdateRef(
            new WorkflowExecution(
                (string) $updateRef->getWorkflowExecution()?->getWorkflowId(),
                $updateRef->getWorkflowExecution()?->getRunId(),
            ),
            $updateRef->getUpdateId(),
        );

        if ($outcome === null) {
            // Not completed
            return new StartUpdateOutput($updateRefDto, false, null);
        }

        $failure = $outcome->getFailure();
        $success = $outcome->getSuccess();


        if ($success !== null) {
            $values = EncodedValues::fromPayloads($success, $this->converter);
            $values = $values->withSerializationContext(
                new WorkflowSerializationContext($namespace, $workflowExecution->getID()),
            );

            return new StartUpdateOutput($updateRefDto, true, $values);
        }

        if ($failure !== null) {
            $execution = $updateRef->getWorkflowExecution();
            $cause = FailureConverter::mapFailureToException($failure, $this->converter);
            $cause = $cause->withSerializationContext(
                new WorkflowSerializationContext($namespace, $workflowExecution->getID()),
            );

            throw new WorkflowUpdateException(
                null,
                $execution === null
                    ? $workflowExecution
                    : new WorkflowExecution($execution->getWorkflowId(), $execution->getRunId()),
                workflowType: $workflowType,
                updateId: $updateRef->getUpdateId(),
                updateName: $updateName,
                previous: $cause,
            );
        }

        throw new \RuntimeException(
            \sprintf(
                'Received unexpected outcome from update request: %s',
                $outcome->getValue(),
            ),
        );
    }
}
