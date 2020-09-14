<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

class SocketStream
{
    public static function create(string $uri, array $context = [])
    {
        $timeout = (int)\ini_get('default_socket_timeout');
        $flags = \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT;

        $ctx = \stream_context_create([
            'socket' => $context,
        ]);

        $socket = \stream_socket_client($uri, $code, $error, $timeout, $flags, $ctx);
        \stream_set_blocking($socket, false);

        return $socket;
    }
}
