<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Client\ClientConnection;
use Temporal\Client\Client\ClientInterface;
use Temporal\Client\Worker\Transport\RpcConnectionInterface;

final class Client extends ClientConnection
{
    /**
     * @param RpcConnectionInterface $rpc
     */
    private function __construct(RpcConnectionInterface $rpc)
    {
        parent::__construct($rpc);
    }

    /**
     * @param RpcConnectionInterface $rpc
     * @return static
     */
    public static function using(RpcConnectionInterface $rpc): self
    {
        return new self($rpc);
    }

    /**
     * @param non-empty-array<RpcConnectionInterface> $connections
     * @param callable(ClientInterface)|null $then
     * @return iterable<ClientInterface>
     */
    public static function on(array $connections, callable $then = null): iterable
    {
        $then ??= static fn (ClientInterface $_) => null;

        foreach ($connections as $connection) {
            $client = new self($connection);
            $then($client);

            yield $client;
        }
    }
}
