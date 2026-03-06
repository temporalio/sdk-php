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
 * Mutable context for {@see ConnectionPluginInterface::configureServiceClient()}.
 *
 * Allows plugins to replace or decorate the service client, e.g.:
 * - Set API key via {@see ServiceClientInterface::withAuthKey()}
 * - Add gRPC metadata via {@see ServiceClientInterface::withContext()}
 */
final class ConnectionPluginContext
{
    public function __construct(
        private ServiceClientInterface $serviceClient,
    ) {}

    public function getServiceClient(): ServiceClientInterface
    {
        return $this->serviceClient;
    }

    public function setServiceClient(ServiceClientInterface $serviceClient): self
    {
        $this->serviceClient = $serviceClient;
        return $this;
    }
}
