<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common\Versioning;

/**
 * Represents the override of a worker's versioning behavior for a workflow execution.
 *
 * @since SDK 2.16.0
 * @since RoadRunner 2025.1.3
 * @internal Experimental
 */
final class VersioningOverride
{
    private function __construct(
        public readonly VersioningBehavior $behavior,
        public readonly ?WorkerDeploymentVersion $version = null,
    ) {}

    /**
     * The Workflow will be pinned to a specific deployment version.
     */
    public static function pinned(WorkerDeploymentVersion $version): self
    {
        return new self(
            VersioningBehavior::Pinned,
            $version,
        );
    }

    /**
     * The Workflow will auto-upgrade to the current deployment version on the next workflow task.
     */
    public static function autoUpgrade(): self
    {
        return new self(
            VersioningBehavior::AutoUpgrade,
        );
    }
}
