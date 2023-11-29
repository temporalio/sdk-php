<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Action\ScheduleAction;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalOneOf;
use Temporal\Internal\Traits\CloneWith;

/**
 * @see \Temporal\Api\Schedule\V1\Schedule
 */
final class Schedule
{
    use CloneWith;

    #[Marshal]
    public readonly ScheduleSpec $spec;

    #[MarshalOneOf(
        cases: ['start_workflow' => StartWorkflowAction::class],
        of: ScheduleAction::class,
        nullable: true,
    )]
    public readonly ?ScheduleAction $action;

    #[Marshal]
    public readonly SchedulePolicies $policies;

    #[Marshal]
    public readonly ScheduleState $state;

    private function __construct()
    {
        $this->action = null;
        $this->spec = ScheduleSpec::new();
        $this->policies = SchedulePolicies::new();
        $this->state = ScheduleState::new();
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Available types of actions:
     * - {@see StartWorkflowAction} - start a Workflow
     */
    public function withAction(?ScheduleAction $action): self
    {
        /** @see self::$action */
        return $this->with('action', $action);
    }

    public function withSpec(ScheduleSpec $spec): self
    {
        /** @see self::$spec */
        return $this->with('spec', $spec);
    }

    public function withPolicies(SchedulePolicies $policies): self
    {
        /** @see self::$policies */
        return $this->with('policies', $policies);
    }

    public function withState(ScheduleState $state): self
    {
        /** @see self::$state */
        return $this->with('state', $state);
    }
}
