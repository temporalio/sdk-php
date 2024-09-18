<?php

declare(strict_types=1);

namespace Temporal\Client;

if (!\class_exists(\Temporal\Client\Workflow\CountWorkflowExecutions::class)) {
    /**
     * @deprecated use {@see \Temporal\Client\Workflow\CountWorkflowExecutions} instead. Will be removed in the future.
     */
    class CountWorkflowExecutions {}
}
