<?php

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Internal\Traits\CloneWith;

/**
 * Feature flags help to smoothly introduce behavior changes that may affect existing workflows.
 * Also, there may be experimental features that are in the testing phase.
 *
 * The flags should be set before the SDK classes are initialized.
 */
final class FeatureFlags
{
    use CloneWith;

    /**
     * @param bool $warnOnWorkflowUnfinishedHandlers Warn about running Signal and Update handlers on Workflow finish.
     * @param bool $workflowDeferredHandlerStart Start the Workflow handler after all signals of the same tick
     *        are processed.
     */
    private function __construct(
        public readonly bool $warnOnWorkflowUnfinishedHandlers = true,
        public readonly bool $workflowDeferredHandlerStart = false,
    ) {}

    /**
     * Create a new instance with values that are compatible with projects that use the SDK before version 2.11.0.
     */
    public static function createLegacy(): self
    {
        return new self(
            warnOnWorkflowUnfinishedHandlers: true,
            workflowDeferredHandlerStart: false,
        );
    }

    /**
     * Create a new instance that activates all the behaviors that are recommended for new projects.
     */
    public static function createDefaults(): self
    {
        return new self(
            warnOnWorkflowUnfinishedHandlers: true,
            workflowDeferredHandlerStart: true,
        );
    }

    /**
     * Warn about running Signal and Update handlers on Workflow finish.
     * It uses `error_log()` function to output a warning message.
     *
     * @since 2.11.0
     */
    public function withWarnOnWorkflowUnfinishedHandlers(bool $value = true): self
    {
        /** @see self::$warnOnWorkflowUnfinishedHandlers */
        return $this->with('warnOnWorkflowUnfinishedHandlers', $value);
    }

    /**
     * Workflow handler must be called after all signals of the same tick are processed.
     * Set to TRUE to enable this behavior.
     *
     * @since 2.11.0
     * @experimental
     * @link https://github.com/temporalio/sdk-php/issues/457
     */
    public function withWorkflowDeferredHandlerStart(bool $value = true): self
    {
        /** @see self::$workflowDeferredHandlerStart */
        return $this->with('workflowDeferredHandlerStart', $value);
    }
}
