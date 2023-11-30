<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Info;

use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Workflow\WorkflowType;

/**
 * @see \Temporal\Api\Schedule\V1\ScheduleListInfo
 */
final class ScheduleListInfo
{
    #[Marshal(name: 'spec')]
    public readonly ScheduleSpec $spec;

    #[Marshal(name: 'workflow_type')]
    public readonly WorkflowType $workflowType;

    #[Marshal(name: 'notes')]
    public readonly string $notes;

    #[Marshal(name: 'paused')]
    public readonly bool $paused;

    /**
     * @var list<ScheduleActionResult>
     */
    #[MarshalArray(name: 'recent_actions', of: ScheduleActionResult::class)]
    public readonly array $recentActions;

    /**
     * Future action times
     *
     * @var list<\DateTimeImmutable>
     */
    #[MarshalArray(name: 'future_action_times', of: \DateTimeImmutable::class)]
    public readonly array $futureActionTimes;

    /**
     * The DTO is a result of a query, so it is not possible to create it manually.
     */
    private function __construct()
    {
    }
}
