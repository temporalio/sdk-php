<?php

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Exception\DestructMemorizedInstanceException;
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
     * This behavior is not correct and will be improved by default in the next major SDK version.
     *
     * To fix the behavior now, set this flag to TRUE. In this case, be aware of the following:
     * - If you start an abandoned Child Workflow in the main Workflow scope, it may miss
     *   the cancellation signal if you await only on the Child Workflow.
     * - If you start an abandoned Child Workflow in an async scope {@see Workflow::async()},
     *   that is later canceled, the Child Workflow will not be affected.
     * - You still can cancel abandoned Child Workflows manually by calling {@see WorkflowStubInterface::cancel()}.
     *
     * @see Workflow\ParentClosePolicy::Abandon
     *
     * @since SDK 2.16.0
     */
    public static bool $cancelAbandonedChildWorkflows = true;

    /**
     * Throw {@see DestructMemorizedInstanceException} when a Workflow instance is destructed.
     *
     * @since SDK 2.16.0
     */
    public static bool $throwDestructMemorizedInstanceException = true;
}
