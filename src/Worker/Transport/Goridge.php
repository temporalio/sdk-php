<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Worker\Transport;

use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Temporal\Exception\TransportException;

final class Goridge implements RPCConnectionInterface
{
    private RPCInterface $rpc;

    /**
     * @param RelayInterface $relay
     */
    public function __construct(RelayInterface $relay)
    {
        $this->rpc = new RPC($relay);
    }

    /**
     * @param string $method
     * @param mixed $payload
     * @return mixed
     *
     * @throws TransportException
     */
    public function call(string $method, $payload)
    {
        try {
            return $this->rpc->call($method, $payload);
        } catch (RPCException $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
