<?php

namespace Temporal\Worker\Transport;

use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Temporal\Exception\TransportException;

// todo: deprecate
final class Goridge implements RpcConnectionInterface
{
    private RPCInterface $rpc;

    public function __construct(RelayInterface $relay)
    {
        // todo: add prefix (?)
        $this->rpc = new RPC($relay);
    }

    /**
     * @param string $method
     * @param mixed $payload
     * @return mixed
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
