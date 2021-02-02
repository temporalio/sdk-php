<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

use Spiral\Goridge\Relay;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
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
     * @param EnvironmentInterface|null $env
     * @return RPCConnectionInterface
     */
    public static function create(EnvironmentInterface $env = null): RPCConnectionInterface
    {
        $env ??= Environment::fromGlobals();

        return new self(Relay::create($env->getRPCAddress()));
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
