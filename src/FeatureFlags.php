<?php

declare(strict_types=1);

namespace Temporal;

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
}
