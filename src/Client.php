<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\ClientOptions;
use Temporal\Worker\Transport\RpcConnectionInterface;

final class Client
{
    /**
     * @param RpcConnectionInterface $rpc
     * @param ClientOptions|null $options
     * @return WorkflowClientInterface
     */
    public static function create(RpcConnectionInterface $rpc, ClientOptions $options = null): WorkflowClientInterface
    {
        return new Client($rpc, $options ?? new ClientOptions());
    }
}
