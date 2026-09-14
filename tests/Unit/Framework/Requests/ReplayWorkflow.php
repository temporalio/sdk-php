<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\Common\Uuid;
use Temporal\DataConverter\EncodedValues;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * Same as {@see StartWorkflow}, but the worker is told that it replays the history.
 *
 * @internal
 */
final class ReplayWorkflow extends ServerRequest
{
    public function __construct(string $runId, string $workflowType, ...$args)
    {
        $path = \explode('\\', $workflowType);
        $info = [
            'WorkflowExecution' => [
                'ID' => Uuid::v4(),
                'RunID' => $runId,
            ],
            'WorkflowType' => [
                'Name' => \array_pop($path),
            ],
        ];

        parent::__construct(
            name: 'StartWorkflow',
            info: new TickInfo(new \DateTimeImmutable(), isReplaying: true),
            options: ['info' => $info],
            payloads: EncodedValues::fromValues($args),
        );
    }
}
