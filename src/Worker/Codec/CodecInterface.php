<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Codec;

use Temporal\Exception\ProtocolException;
use Temporal\Worker\Command\CommandInterface;

interface CodecInterface
{
    /**
     * @param iterable<CommandInterface> $commands
     * @return string
     * @throws ProtocolException
     */
    public function encode(iterable $commands): string;

    /**
     * @param string $message
     * @return iterable<CommandInterface>
     * @throws ProtocolException
     */
    public function decode(string $message): iterable;
}
