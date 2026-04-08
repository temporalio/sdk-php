<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

/**
 * Plugin interface for configuring Temporal schedule clients.
 *
 * Plugins that implement either {@see ScheduleClientPluginInterface} and {@see ClientPluginInterface}
 * are automatically propagated from the service stubs to the schedule client.
 *
 * Configuration methods are called in registration order (first registered = first called).
 */
interface ScheduleClientPluginInterface extends PluginInterface
{
    /**
     * Modify schedule client configuration before the client is created.
     *
     * Called in registration order (first plugin registered = first called).
     *
     * @param callable(ScheduleClientPluginContext): void $next Calls the next plugin or the final hook.
     */
    public function configureScheduleClient(ScheduleClientPluginContext $context, callable $next): void;
}
