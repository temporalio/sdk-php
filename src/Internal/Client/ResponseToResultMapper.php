<?php

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionResponse;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
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
        string $workflowType,
        WorkflowExecution $workflowExecution,
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
            return new StartUpdateOutput(
                $updateRefDto,
                true,
                EncodedValues::fromPayloads($success, $this->converter),
            );
        }

        if ($failure !== null) {
            $execution = $updateRef->getWorkflowExecution();
            throw new WorkflowUpdateException(
                null,
                $execution === null
                    ? $workflowExecution
                    : new WorkflowExecution($execution->getWorkflowId(), $execution->getRunId()),
                workflowType: $workflowType,
                updateId: $updateRef->getUpdateId(),
                updateName: $updateName,
                previous: FailureConverter::mapFailureToException($failure, $this->converter),
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
