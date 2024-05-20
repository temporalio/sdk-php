<?php

declare(strict_types=1);

namespace Temporal\Worker;

if (!\class_exists(\Temporal\Workflow\ChildWorkflowCancellationType::class)) {
    /**
     * @deprecated Use {@see \Temporal\Workflow\ChildWorkflowCancellationType} instead.
     */
    final class ChildWorkflowCancellationType
    {
        public const WAIT_CANCELLATION_COMPLETED = 0x01;
        public const WAIT_CANCELLATION_REQUESTED = 0x02;
        public const TRY_CANCEL = 0x03;
        public const ABANDON = 0x04;
    }
}
