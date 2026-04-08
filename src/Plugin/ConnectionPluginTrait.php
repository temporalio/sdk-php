<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

use Temporal\Client\GRPC\ServiceClientInterface;

/**
 * No-op defaults for {@see ConnectionPluginInterface}.
 *
 * @implements ConnectionPluginInterface
 */
trait ConnectionPluginTrait
{
    public function configureServiceClient(ServiceClientInterface $serviceClient, callable $next): ServiceClientInterface
    {
        return $next($serviceClient);
    }
}
