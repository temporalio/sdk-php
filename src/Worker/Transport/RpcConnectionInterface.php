<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

use Temporal\Exception\TransportException;

// todo: deprecate
interface RpcConnectionInterface
{
    /**
     * @param string $method
     * @param mixed $payload
     * @return mixed
     * @throws TransportException
     */
    public function call(string $method, $payload);
}
