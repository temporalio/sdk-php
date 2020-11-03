<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Protocol;

use Evenement\EventEmitterInterface;
use Temporal\Client\Transport\Protocol\Command\CommandInterface;

/**
 * @implements EventEmitterInterface<Event::ON_*>
 */
interface ProtocolInterface extends EventEmitterInterface
{
    /**
     * TODO specify the type of throwable exception
     *
     * @param string $headers
     * @return array
     * @throws \Throwable
     */
    public function decodeHeaders(string $headers): array;

    /**
     * TODO specify the type of throwable exception
     *
     * Decodes the input string and returns a set of {@see CommandInterface}
     * contained in the passed string argument.
     *
     * @param string $message
     * @return CommandInterface[]
     * @throws \Throwable
     */
    public function decodeCommands(string $message): iterable;

    /**
     * TODO specify the type of throwable exception
     *
     * Encodes a set of {@see CommandInterface} and returns the conversion
     * result as a string.
     *
     * @param CommandInterface[] $commands
     * @return string
     * @throws \Throwable
     */
    public function encode(iterable $commands): string;
}
