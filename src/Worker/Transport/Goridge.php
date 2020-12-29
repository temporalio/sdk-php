<?php

namespace Temporal\Client\Worker\Transport;

use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Temporal\Client\Exception\TransportException;

final class Goridge implements RpcConnectionInterface
{
    /** @var RPCInterface */
    private RPCInterface $rpc;

    public function __construct(RelayInterface $relay)
    {
        // todo: add prefix
        // todo: update to MSGPack
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
