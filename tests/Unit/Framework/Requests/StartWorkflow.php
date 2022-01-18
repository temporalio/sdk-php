<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\Common\Uuid;
use Temporal\DataConverter\EncodedValues;
use Temporal\Worker\Transport\Command\Request;

/**
 * @internal
 */
final class StartWorkflow extends Request
{
    public function __construct(string $runId, string $workflowType, ...$args)
    {
        $info = [
            'WorkflowExecution' => [
                'ID' => Uuid::v4(),
                'RunID' => $runId,
            ],
            'WorkflowType' => [
                'Name' => $this->extractClassShortName($workflowType),
            ]
        ];
        parent::__construct(
            'StartWorkflow',
            ['info' => $info],
            EncodedValues::fromValues($args)
        );
    }

    private function extractClassShortName(string $workflowType): string
    {
        $path = explode('\\', $workflowType);

        return array_pop($path);
    }
}
