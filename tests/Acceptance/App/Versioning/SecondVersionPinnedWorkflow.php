<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Versioning;

use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class SecondVersionPinnedWorkflow
{
    public const WORKFLOW_TYPE = 'Extra_Versioning_Routing_Pinned';

    private ?string $value = null;

    #[WorkflowMethod(name: self::WORKFLOW_TYPE)]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->value !== null);

        return $this->value . '_v2';
    }

    #[SignalMethod(name: SignalName::START)]
    public function start(string $value): void
    {
        $this->value = $value;
    }
}
