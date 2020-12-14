<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Transport;

use Temporal\Client\Exception\TransportException;

/**
 * @psalm-type Headers = array<string, mixed>
 * @psalm-type Message = array { 0: string, 1: Headers }
 */
interface RelayConnectionInterface
{
    /**
     * @var positive-int
     */
    public const MESSAGE_BODY = 0x00;

    /**
     * @var positive-int
     */
    public const MESSAGE_HEADERS = 0x01;

    /**
     * @return Message
     * @throws TransportException
     */
    #[ArrayShape([self::MESSAGE_BODY => 'string', self::MESSAGE_HEADERS => 'array'])]
    public function await(): array;

    /**
     * @param string $message
     * @param Headers $headers
     * @throws TransportException
     */
    public function send(string $message, array $headers = []): void;

    /**
     * @param \Throwable $error
     * @throws TransportException
     */
    public function error(\Throwable $error): void;
}
