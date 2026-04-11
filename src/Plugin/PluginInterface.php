<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

interface PluginInterface
{
    /**
     * Unique name identifying this plugin (e.g., "my-org.tracing").
     * Used for deduplication and diagnostics.
     */
    public function getName(): string;
}
