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
 * Plugin interface for configuring the service client connection.
 *
 * Connection plugins are applied before client-level plugins, allowing
 * them to set API keys, gRPC metadata, TLS context, and other
 * connection-level options.
 */
interface ConnectionPluginInterface extends PluginInterface
{
    /**
     * Modify the service client before it is used by the client.
     *
     * Use this hook to configure connection-level settings such as
     * API keys, gRPC metadata, auth tokens, or context options.
     *
     * @param callable(ConnectionPluginContext): void $next Calls the next plugin or the final hook.
     */
    public function configureServiceClient(ConnectionPluginContext $context, callable $next): void;
}
