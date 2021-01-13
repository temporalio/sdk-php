<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverterInterface;

final class Client
{
    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions|null $options
     * @param DataConverterInterface|null $converter
     * @return WorkflowClientInterface
     */
    public static function create(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ): WorkflowClientInterface {
        return new WorkflowClient($serviceClient, $options ?? new ClientOptions(), $converter);
    }
}
