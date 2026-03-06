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
 * Combined plugin interface for bundles that configure connection, client, schedule client, and worker.
 *
 * Implementing this interface is optional — plugins may implement only
 * {@see ConnectionPluginInterface}, {@see ClientPluginInterface},
 * {@see ScheduleClientPluginInterface}, or {@see WorkerPluginInterface} as needed.
 */
interface TemporalPluginInterface extends ConnectionPluginInterface, ClientPluginInterface, ScheduleClientPluginInterface, WorkerPluginInterface {}
