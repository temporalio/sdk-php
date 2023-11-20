<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use Temporal\Client\Schedule\Action\NewWorkflowExecutionInfo;
use Temporal\Client\Schedule\Spec\SchedulePolicies;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalOneOf;

/**
 * @see \Temporal\Api\Schedule\V1\Schedule
 */
final class Schedule
{
    #[Marshal]
    public readonly ScheduleSpec $spec;

    #[MarshalOneOf(
        cases: ['start_workflow' => NewWorkflowExecutionInfo::class],
        of: ScheduleAction::class,
        nullable: true,
    )]
    public readonly ?ScheduleAction $action;

    #[Marshal]
    public readonly SchedulePolicies $policies;

    #[Marshal]
    public readonly ScheduleState $state;
}
