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
use Temporal\Client\Worker\Transport\ConnectionInterface;

final class Client extends ClientConnection
{
    /**
     * @param ConnectionInterface $default
     */
    private function __construct(ConnectionInterface $default)
    {
        parent::__construct($default);
    }

    /**
     * @param ConnectionInterface $connection
     * @return static
     */
    public static function using(ConnectionInterface $connection): self
    {
        return new self($connection);
    }

    /**
     * @param non-empty-array<ConnectionInterface> $connections
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
