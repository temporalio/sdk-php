<?php

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Workflow;

/**
 * Feature flags help to smoothly introduce behavior changes that may affect existing workflows.
 * Also, there may be experimental features that are in the testing phase.
 *
 * The flags should be set before the SDK classes are initialized.
 */
final class FeatureFlags
{
    /**
     * Workflow handler must be called after all signals of the same tick are processed.
     * Set to TRUE to enable this behavior.
     *
     * @experimental
     * @since SDK 2.11.0
     * @link https://github.com/temporalio/sdk-php/issues/457
     */
    public static bool $workflowDeferredHandlerStart = false;

    /**
     * Warn about running Signal and Update handlers on Workflow finish.
     * It uses {@see Workflow::getLogger()} to output a warning message.
     *
     * @since SDK 2.11.0
     */
    public static bool $warnOnWorkflowUnfinishedHandlers = true;

    /**
     * When a parent workflow is canceled, it will also cancel all its Child Workflows, including abandoned ones.
     * To fix the behavior of the previous SDK versions and not cancel abandoned Child Workflows,
     * set this flag to FALSE.
     *
     * @see Workflow\ParentClosePolicy::Abandon
     *
     * @since SDK 2.16.0
     */
    public static bool $cancelAbandonedChildWorkflows = true;
}
