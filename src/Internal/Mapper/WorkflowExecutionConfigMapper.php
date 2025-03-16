<?php

declare(strict_types=1);

namespace Temporal\Internal\Mapper;

use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Workflow\V1\WorkflowExecutionConfig;
use Temporal\Client\Workflow\UserMetadata;
use Temporal\Common\TaskQueue\TaskQueue;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\WorkflowExecutionConfig as ExecutionConfigDto;

final class WorkflowExecutionConfigMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {}

    public function fromMessage(WorkflowExecutionConfig $message): ExecutionConfigDto
    {
        /** @psalm-suppress InaccessibleProperty */
        $executionTimeout = $message->getWorkflowExecutionTimeout();
        $executionTimeout !== null and $executionTimeout = DateInterval::parse($executionTimeout);

        $metadata = $message->getUserMetadata();

        return new ExecutionConfigDto(
            taskQueue: TaskQueue::new($message->getTaskQueue()?->getName() ?? ''),
            workflowExecutionTimeout: $executionTimeout,
            workflowRunTimeout: $executionTimeout,
            defaultWorkflowTaskTimeout: $executionTimeout,
            userMetadata: new UserMetadata(
                $this->fromPayload($metadata?->getSummary(), ''),
                $this->fromPayload($metadata?->getDetails(), ''),
            ),
        );
    }

    private function fromPayload(?Payload $payload, mixed $default = null): mixed
    {
        return $payload === null
            ? $default
            : $this->converter->fromPayload($payload, 'string');
    }
}
